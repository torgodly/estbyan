<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\InsuranceCardNumber;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InsuranceCardNumberAssigner
{
    public function fillEmployee(Employee $employee): void
    {
        if (InsuranceCardNumber::isValid($employee->card_number)) {
            return;
        }

        $employee->card_number = $this->nextEmployeeCardNumber();
    }

    public function ensureEmployee(Employee $employee): ?string
    {
        $this->fillEmployee($employee);

        if ($employee->exists && $employee->isDirty('card_number')) {
            $employee->save();
        }

        return $employee->card_number;
    }

    public function fillBeneficiary(Beneficiary $beneficiary): void
    {
        if (InsuranceCardNumber::isValid($beneficiary->card_number)) {
            return;
        }

        $employee = $this->employeeFor($beneficiary);

        if ($employee === null) {
            return;
        }

        $this->ensureEmployee($employee);

        if (! InsuranceCardNumber::isValid($employee->card_number)) {
            return;
        }

        $reused = $this->reusedMemberNumber($employee, $beneficiary);

        $beneficiary->card_number = $reused ?? $this->nextMemberCardNumber($employee);
    }

    public function ensureBeneficiary(Beneficiary $beneficiary): void
    {
        $this->fillBeneficiary($beneficiary);

        if ($beneficiary->exists && $beneficiary->isDirty('card_number')) {
            $beneficiary->save();
        }
    }

    public function nextEmployeeCardNumber(): string
    {
        return DB::transaction(function (): string {
            $latest = Employee::query()
                ->whereNotNull('card_number')
                ->orderByDesc('card_number')
                ->lockForUpdate()
                ->value('card_number');

            $nextStem = InsuranceCardNumber::isValid($latest)
                ? ((int) InsuranceCardNumber::stem($latest)) + 1
                : 1;

            if ($nextStem > 999999) {
                throw new RuntimeException('Insurance card numbers are exhausted.');
            }

            return InsuranceCardNumber::compose($nextStem, 0);
        });
    }

    public function nextMemberCardNumber(Employee $employee): string
    {
        $stem = InsuranceCardNumber::stem($employee->card_number);

        return DB::transaction(function () use ($stem): string {
            $latest = Beneficiary::query()
                ->where('card_number', 'like', $stem.'%')
                ->orderByDesc('card_number')
                ->lockForUpdate()
                ->value('card_number');

            $nextIndex = InsuranceCardNumber::isValid($latest)
                ? InsuranceCardNumber::memberIndex($latest) + 1
                : 1;

            if ($nextIndex > 99) {
                throw new RuntimeException('This employee already has 99 family card numbers.');
            }

            return InsuranceCardNumber::compose($stem, $nextIndex);
        });
    }

    private function reusedMemberNumber(Employee $employee, Beneficiary $beneficiary): ?string
    {
        $stem = InsuranceCardNumber::stem($employee->card_number);

        $query = Beneficiary::query()
            ->where('card_number', 'like', $stem.'%')
            ->whereNotNull('card_number');

        if ($beneficiary->exists) {
            $query->whereKeyNot($beneficiary->id);
        }

        if (filled($beneficiary->national_id)) {
            $query->where('national_id', $beneficiary->national_id);
        } elseif (filled($beneficiary->passport_number)) {
            $query->where('passport_number', $beneficiary->passport_number);
        } elseif (filled($beneficiary->full_name) && $beneficiary->date_of_birth) {
            $query->where('full_name', $beneficiary->full_name)
                ->whereDate('date_of_birth', $beneficiary->date_of_birth);
        } else {
            return null;
        }

        $number = $query->value('card_number');

        return InsuranceCardNumber::isValid($number) ? $number : null;
    }

    private function employeeFor(Beneficiary $beneficiary): ?Employee
    {
        $beneficiary->loadMissing('medicalRegistration.employee');

        $registration = $beneficiary->medicalRegistration
            ?? MedicalRegistration::query()->find($beneficiary->medical_registration_id);

        return $registration?->employee;
    }
}
