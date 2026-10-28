<?php

namespace App\Console\Commands;

use App\Enums\BeneficiaryCancellationReason;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-time decisions for the 17 manual review flags of 2026-10-28, taken from the records
 * themselves because nobody was available to review them. Every record is checked against
 * the values the decision was based on; a decision whose records changed is skipped.
 *
 * @see docs/cancelled-family-members.md
 */
#[Signature('review:apply-decisions-2026-10 {--dry-run : Show what would change without changing data} {--force : Skip the confirmation prompt}')]
#[Description('Apply the decided outcome of the October 2026 manual review flags and close them')]
class ApplyReviewDecisionsCommand extends Command
{
    /**
     * @var list<array{action: string, beneficiary_id?: int, employee_number?: string, national_id: string, also_close_beneficiary_id?: int, also_close_employee_number?: string, note: string}>
     */
    private const DECISIONS = [
        ['action' => 'keep', 'beneficiary_id' => 26164, 'national_id' => '219640289487', 'note' => 'ليست تكراراً: الأم (مواليد 1964) سُجلت باسم ابنها خطأً.'],
        ['action' => 'keep', 'beneficiary_id' => 23471, 'national_id' => '119650315704', 'note' => 'ليس تكراراً: الأب (مواليد 1965) سُجل باسم ابنه خطأً.'],
        ['action' => 'keep', 'beneficiary_id' => 45594, 'national_id' => '120040661383', 'note' => 'أُبقي: على الأرجح توأم الموظف محمد (الفرق يوم واحد) وأُدخل رقمه الوطني خطأً. لا تُزال تغطية طفل حقيقي.'],
        ['action' => 'delete', 'beneficiary_id' => 49459, 'national_id' => '219590162687', 'also_close_beneficiary_id' => 61697, 'note' => 'نفس الأم سجّلها الأخوان عادل وابوبكر فريفر. أُبقيت النسخة المطبوعة (ملف 036010) وحُذفت غير المطبوعة (ملف 036004).'],
        ['action' => 'deactivate', 'employee_number' => '004131', 'national_id' => '120000384414', 'also_close_employee_number' => '040285', 'note' => 'سجل موظف مكرر بلا طلب. أُبقي السجل 040285 الذي عليه الطلب، وأُوقف 004131.'],
        ['action' => 'deactivate', 'employee_number' => '030089', 'national_id' => '120640287198', 'also_close_employee_number' => '007198', 'note' => 'سجل موظف مكرر بلا طلب وسنة الميلاد في رقمه الوطني غير صحيحة (2064). أُبقي 007198 وأُوقف 030089.'],
        ['action' => 'deactivate', 'employee_number' => '040235', 'national_id' => '120060728368', 'also_close_employee_number' => '122303', 'note' => 'سجل موظف مكرر بلا طلب. أُبقي 122303 الذي عليه الطلب، وأُوقف 040235.'],
        ['action' => 'deactivate', 'employee_number' => '001387', 'national_id' => '219820013038', 'also_close_employee_number' => '040186', 'note' => 'سجل موظف مكرر بلا طلب. أُبقي 040186 الذي عليه الطلب، وأُوقف 001387.'],
        ['action' => 'cancel', 'beneficiary_id' => 60342, 'national_id' => '219870429691', 'note' => 'نفس الموظفة 014031 (نفس الاسم والمكتب، وزوجها 014030). أُلغيت كفرد عائلة.'],
        ['action' => 'cancel', 'beneficiary_id' => 11093, 'national_id' => '220050081723', 'note' => 'نفس الموظفة 001612 (نفس الاسم والمكتب، وزوجها 001613). أُلغيت كفرد عائلة.'],
        ['action' => 'cancel', 'beneficiary_id' => 61520, 'national_id' => '119800498800', 'note' => 'نفس الموظف 015008 (نفس الرقم الوطني، فرق إملائي فقط). أُلغي كفرد عائلة.'],
        ['action' => 'cancel', 'beneficiary_id' => 48424, 'national_id' => '219790172408', 'note' => 'نفس الموظفة 001572 (نفس الرقم الوطني، فرق إملائي فقط). أُلغيت كفرد عائلة.'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $planned = [];

        foreach (self::DECISIONS as $decision) {
            $problem = $this->problem($decision);

            $planned[] = [$decision, $problem];
            $this->line(sprintf(
                '%s %-10s %s',
                $problem === null ? '<info>OK  </info>' : '<error>SKIP</error>',
                $decision['action'],
                $problem ?? $decision['note'],
            ));
        }

        $ready = array_filter($planned, fn (array $item): bool => $item[1] === null);

        $this->components->info(sprintf('%d decision(s) ready, %d skipped.', count($ready), count($planned) - count($ready)));

        if ($dryRun || $ready === []) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Apply these decisions?', false)) {
            $this->components->warn('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        $closed = 0;

        foreach ($ready as [$decision]) {
            $closed += DB::transaction(fn (): int => $this->apply($decision));
        }

        $this->components->info(sprintf('Applied %d decision(s) and closed %d review flag(s).', count($ready), $closed));

        return self::SUCCESS;
    }

    /**
     * Why this decision cannot be applied safely, or null when the records are as expected.
     *
     * @param  array<string, mixed>  $decision
     */
    private function problem(array $decision): ?string
    {
        if ($decision['action'] === 'deactivate') {
            $employee = Employee::query()->where('employee_number', $decision['employee_number'])->first();
            $kept = Employee::query()->where('employee_number', $decision['also_close_employee_number'])->first();

            return match (true) {
                $employee === null || $kept === null => 'Employee '.$decision['employee_number'].' or '.$decision['also_close_employee_number'].' not found.',
                trim((string) $employee->national_id) !== $decision['national_id'] => 'Employee '.$decision['employee_number'].' national ID changed.',
                $employee->medicalRegistrations()->exists() => 'Employee '.$decision['employee_number'].' now has a registration.',
                $employee->cardIsPrinted() || $employee->cardsAreDelivered() => 'Employee '.$decision['employee_number'].' card is printed or delivered.',
                ! $kept->medicalRegistrations()->exists() => 'Kept employee '.$decision['also_close_employee_number'].' has no registration.',
                $this->openFlags($decision)->isEmpty() => 'No open review flag (already handled).',
                default => null,
            };
        }

        $beneficiary = Beneficiary::queryWithCancelled()->find($decision['beneficiary_id']);

        return match (true) {
            $beneficiary === null => 'Family member #'.$decision['beneficiary_id'].' not found.',
            $beneficiary->isCancelled() => 'Family member #'.$decision['beneficiary_id'].' is already cancelled.',
            trim((string) $beneficiary->national_id) !== $decision['national_id'] => 'Family member #'.$decision['beneficiary_id'].' national ID changed.',
            $decision['action'] === 'delete' && $beneficiary->cardIsPrinted() => 'Family member #'.$decision['beneficiary_id'].' card is now printed.',
            $decision['action'] === 'delete' && Beneficiary::query()->find($decision['also_close_beneficiary_id']) === null => 'Kept family member #'.$decision['also_close_beneficiary_id'].' not found.',
            $this->openFlags($decision)->isEmpty() => 'No open review flag (already handled).',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function apply(array $decision): int
    {
        $flags = $this->openFlags($decision);

        foreach ($flags as $flag) {
            $flag->resolve(null, 'قرار تلقائي: '.$decision['note']);
        }

        if ($decision['action'] === 'deactivate') {
            Employee::query()
                ->where('employee_number', $decision['employee_number'])
                ->firstOrFail()
                ->forceFill(['is_active' => false])
                ->save();

            return $flags->count();
        }

        $beneficiary = Beneficiary::queryWithCancelled()->findOrFail($decision['beneficiary_id']);
        $registration = $beneficiary->medicalRegistration;

        match ($decision['action']) {
            'cancel' => $beneficiary->cancel(BeneficiaryCancellationReason::EmployeeDuplicate),
            'delete' => $beneficiary->delete(),
            default => null,
        };

        if ($registration instanceof MedicalRegistration && $decision['action'] !== 'keep') {
            $registration->forceFill(['beneficiaries_count' => $registration->beneficiaries()->count()])->saveQuietly();
        }

        return $flags->count();
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return Collection<int, ReviewFlag>
     */
    private function openFlags(array $decision): Collection
    {
        $employeeMorph = (new Employee)->getMorphClass();
        $beneficiaryMorph = (new Beneficiary)->getMorphClass();

        $employeeIds = Employee::query()
            ->whereIn('employee_number', array_filter([$decision['employee_number'] ?? null, $decision['also_close_employee_number'] ?? null]))
            ->pluck('id')
            ->all();
        $beneficiaryIds = array_filter([$decision['beneficiary_id'] ?? null, $decision['also_close_beneficiary_id'] ?? null]);

        return ReviewFlag::query()
            ->open()
            ->where(function ($query) use ($employeeMorph, $beneficiaryMorph, $employeeIds, $beneficiaryIds): void {
                $query->where(fn ($employees) => $employees->where('flaggable_type', $employeeMorph)->whereIn('flaggable_id', $employeeIds ?: [0]))
                    ->orWhere(fn ($family) => $family->where('flaggable_type', $beneficiaryMorph)->whereIn('flaggable_id', $beneficiaryIds ?: [0]));
            })
            ->get();
    }
}
