<?php

namespace App\Services;

use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\EmployeeInsuranceCard;
use App\Support\InsuranceCardNumber;
use App\Support\InsuranceCardScanHit;

class InsuranceCardScanService
{
    public function __construct(private InsuranceCardPrintMarker $marker) {}

    public function find(string $raw): ?InsuranceCardScanHit
    {
        $digits = InsuranceCardNumber::normalize($raw);

        if ($digits === null) {
            return null;
        }

        $employee = Employee::query()
            ->with(['latestSubmittedRegistration', 'latestMedicalRegistration'])
            ->where('card_number', $digits)
            ->first();

        if ($employee) {
            return $this->hitFromEmployee($employee, $digits);
        }

        $beneficiary = Beneficiary::query()
            ->with('medicalRegistration.employee')
            ->where('card_number', $digits)
            ->orderByDesc('id')
            ->first();

        if ($beneficiary?->medicalRegistration?->employee === null) {
            return null;
        }

        return $this->hitFromBeneficiary($beneficiary, $digits);
    }

    /**
     * @param  list<array<string, mixed>>  $scanned
     * @return list<array<string, mixed>>
     */
    public function groups(array $scanned): array
    {
        if ($scanned === []) {
            return [];
        }

        $byEmployee = collect($scanned)->groupBy('employee_id');
        $employeeIds = $byEmployee->keys()->map(fn (mixed $id): int => (int) $id)->all();

        $employees = Employee::query()
            ->with([
                'latestSubmittedRegistration.beneficiaries',
                'latestMedicalRegistration.beneficiaries',
            ])
            ->whereIn('id', $employeeIds)
            ->get()
            ->keyBy('id');

        $order = collect($scanned)->pluck('employee_id')->reverse()->unique()->values();

        return $order->map(function (mixed $employeeId) use ($byEmployee, $employees): array {
            $employeeId = (int) $employeeId;
            $hits = $byEmployee->get($employeeId) ?? collect();
            $employee = $employees->get($employeeId);
            $registration = $this->registrationForGroup($hits->all(), $employee);

            $scannedByNumber = $hits->keyBy('card_number');
            $members = [];

            if ($registration) {
                $registration->loadMissing(['employee', 'beneficiaries']);

                foreach (EmployeeInsuranceCard::collection($registration) as $card) {
                    $digits = InsuranceCardNumber::normalize($card->reference);
                    $hit = $digits ? $scannedByNumber->get($digits) : null;

                    $members[] = [
                        'name' => $card->name,
                        'role_label' => $card->jobTitle,
                        'card_number' => $digits,
                        'card_label' => $card->reference,
                        'scanned' => $hit !== null,
                        'is_printed' => $card->isPrinted,
                        'person_key' => $card->personKey,
                    ];

                    if ($digits) {
                        $scannedByNumber->forget($digits);
                    }
                }
            }

            foreach ($scannedByNumber as $hit) {
                $members[] = [
                    'name' => $hit['name'],
                    'role_label' => $hit['role_label'],
                    'card_number' => $hit['card_number'],
                    'card_label' => InsuranceCardNumber::display($hit['card_number']),
                    'scanned' => true,
                    'is_printed' => (bool) $hit['is_printed'],
                    'person_key' => $hit['person_key'],
                ];
            }

            $employeeHit = $hits->firstWhere('kind', 'employee');

            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee?->full_name
                    ?: ($employeeHit['name'] ?? $hits->first()['name'] ?? '—'),
                'registration_id' => $registration?->id,
                'registration_url' => $registration
                    ? MedicalRegistrationResource::getUrl('view', ['record' => $registration])
                    : null,
                'reference' => $registration?->reference_number,
                'scanned_count' => $hits->count(),
                'expected_count' => count($members),
                'complete' => count($members) > 0 && $hits->count() >= count($members),
                'members' => $members,
            ];
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $scanned
     */
    public function markPrinted(array $scanned): int
    {
        foreach ($scanned as $hit) {
            if (($hit['kind'] ?? '') === 'employee') {
                Employee::query()->find($hit['employee_id'] ?? null)?->markCardPrinted();

                continue;
            }

            $registration = MedicalRegistration::query()->find($hit['registration_id'] ?? null);

            if ($registration && filled($hit['person_key'] ?? null)) {
                $this->marker->mark($registration, $hit['person_key']);
            }
        }

        return count($scanned);
    }

    private function hitFromEmployee(Employee $employee, string $digits): InsuranceCardScanHit
    {
        $registration = $employee->latestSubmittedRegistration ?? $employee->latestMedicalRegistration;

        return new InsuranceCardScanHit(
            cardNumber: $digits,
            kind: 'employee',
            employeeId: $employee->id,
            registrationId: $registration?->id,
            beneficiaryId: null,
            name: $employee->full_name ?: ($registration?->full_name ?: '—'),
            roleLabel: 'موظف',
            personKey: 'employee',
            isPrinted: $employee->cardIsPrinted(),
        );
    }

    private function hitFromBeneficiary(Beneficiary $beneficiary, string $digits): InsuranceCardScanHit
    {
        $registration = $beneficiary->medicalRegistration;
        $employee = $registration->employee;

        return new InsuranceCardScanHit(
            cardNumber: $digits,
            kind: 'beneficiary',
            employeeId: $employee->id,
            registrationId: $registration->id,
            beneficiaryId: $beneficiary->id,
            name: $beneficiary->full_name ?: '—',
            roleLabel: $beneficiary->relationship?->label($registration->gender) ?: 'مستفيد',
            personKey: 'beneficiary-'.$beneficiary->id,
            isPrinted: $beneficiary->cardIsPrinted(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     */
    private function registrationForGroup(array $hits, ?Employee $employee): ?MedicalRegistration
    {
        $registrationId = collect($hits)->pluck('registration_id')->filter()->first();

        if ($registrationId) {
            return MedicalRegistration::query()
                ->with(['employee', 'beneficiaries'])
                ->find($registrationId);
        }

        $registration = $employee?->latestSubmittedRegistration ?? $employee?->latestMedicalRegistration;

        return $registration?->loadMissing(['employee', 'beneficiaries']);
    }
}
