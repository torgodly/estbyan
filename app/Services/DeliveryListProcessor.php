<?php

namespace App\Services;

use App\Enums\CardDeliveryRecipient;
use App\Enums\RegistrationStatus;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Support\Facades\DB;

/**
 * Marks families as delivered from a list of employee numbers (e.g. the fully printed
 * report's Excel, sent back by the administration). Matching is exact on the employee
 * number; anything unclear is reported and left untouched.
 *
 * @see docs/cancelled-family-members.md
 */
class DeliveryListProcessor
{
    /**
     * @param  array<int, array{employee_number: string, name: ?string}>  $rows  Keyed by sheet row number.
     * @return array{
     *     delivered: list<array{row: int, employee: Employee}>,
     *     already_delivered: list<array{row: int, employee: Employee}>,
     *     blocked: list<array{row: int, employee: Employee, problem: string}>,
     *     not_found: list<array{row: int, employee_number: string, problem: string}>,
     *     repeated: list<array{row: int, employee_number: string}>
     * }
     */
    public function process(array $rows, ?User $by, CardDeliveryRecipient $to, bool $dryRun = false): array
    {
        $result = [
            'delivered' => [],
            'already_delivered' => [],
            'blocked' => [],
            'not_found' => [],
            'repeated' => [],
        ];

        $employees = Employee::query()
            ->with(['latestSubmittedRegistration.beneficiaries', 'latestMedicalRegistration.beneficiaries'])
            ->whereIn('employee_number', array_column($rows, 'employee_number'))
            ->get()
            ->keyBy('employee_number');

        $seen = [];

        foreach ($rows as $row => $item) {
            $number = $item['employee_number'];

            if (isset($seen[$number])) {
                $result['repeated'][] = ['row' => $row, 'employee_number' => $number];

                continue;
            }

            $seen[$number] = true;
            $employee = $employees->get($number);

            if (! $employee instanceof Employee) {
                $result['not_found'][] = ['row' => $row, 'employee_number' => $number, 'problem' => $this->notFoundReason($number)];

                continue;
            }

            if ($employee->cardsAreDelivered()) {
                $result['already_delivered'][] = ['row' => $row, 'employee' => $employee];

                continue;
            }

            $problem = $this->deliveryProblem($employee, $item['name']);

            if ($problem !== null) {
                $result['blocked'][] = ['row' => $row, 'employee' => $employee, 'problem' => $problem];

                continue;
            }

            $result['delivered'][] = ['row' => $row, 'employee' => $employee];
        }

        if (! $dryRun && $result['delivered'] !== []) {
            throw_if($by === null, 'InvalidArgumentException', 'A user is required to mark cards as delivered.');

            DB::transaction(function () use ($result, $by, $to): void {
                foreach ($result['delivered'] as $item) {
                    $item['employee']->markCardsDelivered($by, $to);
                }
            });
        }

        return $result;
    }

    /**
     * For lists that only have names: a name is turned into an employee number only when it
     * matches exactly one employee (spelling variants ignored, notes after "/" dropped).
     * Close names, shared names and unknown names are returned as uncertain, never guessed.
     *
     * @param  array<int, string>  $namesByRow
     * @return array{
     *     rows: array<int, array{employee_number: string, name: string}>,
     *     uncertain: list<array{row: int, name: string, problem: string}>
     * }
     */
    public function resolveNames(array $namesByRow): array
    {
        $employeesByName = [];

        foreach (Employee::query()->get(['id', 'employee_number', 'full_name']) as $employee) {
            $employeesByName[PersonName::normalize($employee->full_name)][] = $employee;
        }

        $rows = [];
        $uncertain = [];

        foreach ($namesByRow as $row => $rawName) {
            $name = trim(explode('/', $rawName)[0]);
            $name = trim((string) preg_replace('/[^\p{Arabic}\s]/u', ' ', $name));
            $matches = $employeesByName[PersonName::normalize($name)] ?? [];

            if (count($matches) === 1) {
                $rows[$row] = ['employee_number' => $matches[0]->employee_number, 'name' => $name];

                continue;
            }

            $uncertain[] = [
                'row' => $row,
                'name' => $rawName,
                'problem' => $matches === []
                    ? 'لا يوجد موظف بهذا الاسم تماماً (ربما خطأ إملائي).'
                    : 'أكثر من موظف بنفس الاسم: '.collect($matches)->pluck('employee_number')->implode('، '),
            ];
        }

        return ['rows' => $rows, 'uncertain' => $uncertain];
    }

    /**
     * Why this family cannot be marked delivered, or null when it can.
     */
    private function deliveryProblem(Employee $employee, ?string $nameInList): ?string
    {
        if (filled($nameInList) && PersonName::normalize($nameInList) !== PersonName::normalize($employee->full_name)) {
            return sprintf('الاسم في الملف "%s" لا يطابق اسم الموظف "%s".', trim($nameInList), trim((string) $employee->full_name));
        }

        if (! $employee->is_active) {
            return 'الموظف غير نشط.';
        }

        $registration = $employee->latestSubmittedRegistration ?? $employee->latestMedicalRegistration;

        if (! $registration instanceof MedicalRegistration) {
            return 'لا يوجد طلب تسجيل لهذا الموظف.';
        }

        if ($registration->status !== RegistrationStatus::Approved) {
            return 'حالة الطلب: '.$registration->status->label().'.';
        }

        $missing = [];

        if (! $employee->cardIsPrinted()) {
            $missing[] = 'بطاقة الموظف';
        }

        foreach ($registration->beneficiaries as $beneficiary) {
            /** @var Beneficiary $beneficiary */
            if (! $beneficiary->cardIsPrinted()) {
                $missing[] = trim((string) $beneficiary->full_name);
            }
        }

        if ($missing !== []) {
            return 'بطاقات غير مطبوعة: '.implode('، ', $missing).'.';
        }

        $underReview = ReviewFlag::query()
            ->open()
            ->where(function ($query) use ($employee, $registration): void {
                $query->where(fn ($employeeQuery) => $employeeQuery
                    ->where('flaggable_type', $employee->getMorphClass())
                    ->where('flaggable_id', $employee->id))
                    ->orWhere(fn ($familyQuery) => $familyQuery
                        ->where('flaggable_type', (new Beneficiary)->getMorphClass())
                        ->whereIn('flaggable_id', $registration->beneficiaries->modelKeys()));
            })
            ->exists();

        return $underReview
            ? 'الموظف أو أحد أفراد عائلته في صفحة المراجعة اليدوية.'
            : null;
    }

    private function notFoundReason(string $number): string
    {
        $lostZeros = ctype_digit($number)
            && Employee::query()
                ->where('employee_number', 'like', '%'.$number)
                ->get(['employee_number'])
                ->contains(fn (Employee $employee): bool => ltrim($employee->employee_number, '0') === $number);

        return $lostZeros
            ? 'غير موجود. ربما حُذفت الأصفار من بداية الرقم في Excel.'
            : 'لا يوجد موظف بهذا الرقم.';
    }
}
