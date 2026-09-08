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
    public function fillEmployee(Employee $employee, bool $replaceLegacy = false): void
    {
        if (! $this->shouldReplace($employee->card_number, $replaceLegacy)) {
            return;
        }

        $employee->card_number = $this->nextUniqueCardNumber();
    }

    public function ensureEmployee(Employee $employee, bool $replaceLegacy = false): ?string
    {
        $this->fillEmployee($employee, $replaceLegacy);

        if ($employee->exists && $employee->isDirty('card_number')) {
            $employee->save();
        }

        return $employee->card_number;
    }

    public function fillBeneficiary(Beneficiary $beneficiary, bool $replaceLegacy = false): void
    {
        if (! $this->shouldReplace($beneficiary->card_number, $replaceLegacy)) {
            return;
        }

        $employee = $this->employeeFor($beneficiary);

        if ($employee === null) {
            return;
        }

        $this->ensureEmployee($employee);

        $reused = $this->reusedMember($employee, $beneficiary);

        if ($reused) {
            $beneficiary->card_number = $reused->card_number;

            if ($beneficiary->card_printed_at === null) {
                $beneficiary->card_printed_at = $reused->card_printed_at;
            }

            return;
        }

        $beneficiary->card_number = $this->nextUniqueCardNumber();
    }

    public function ensureBeneficiary(Beneficiary $beneficiary, bool $replaceLegacy = false): void
    {
        $this->fillBeneficiary($beneficiary, $replaceLegacy);

        if ($beneficiary->exists && $beneficiary->isDirty('card_number')) {
            $beneficiary->save();
        }
    }

    public function nextUniqueCardNumber(): string
    {
        return DB::transaction(function (): string {
            for ($attempt = 0; $attempt < 25; $attempt++) {
                $number = (string) random_int((int) InsuranceCardNumber::MIN, (int) InsuranceCardNumber::MAX);

                if (! $this->cardNumberTaken($number)) {
                    return $number;
                }
            }

            throw new RuntimeException('Unable to allocate a unique insurance card number.');
        });
    }

    private function shouldReplace(?string $number, bool $replaceLegacy): bool
    {
        if (InsuranceCardNumber::isCurrent($number)) {
            return false;
        }

        if (! $replaceLegacy && InsuranceCardNumber::isValid($number)) {
            return false;
        }

        return true;
    }

    private function reusedMember(Employee $employee, Beneficiary $beneficiary): ?Beneficiary
    {
        $query = Beneficiary::query()
            ->whereHas(
                'medicalRegistration',
                fn ($query) => $query->where('employee_id', $employee->id),
            )
            ->where('card_number', '>=', InsuranceCardNumber::MIN);

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

        $existing = $query->first();

        return $existing && InsuranceCardNumber::isCurrent($existing->card_number)
            ? $existing
            : null;
    }

    private function cardNumberTaken(string $number): bool
    {
        return Employee::query()->where('card_number', $number)->exists()
            || Beneficiary::query()->where('card_number', $number)->exists();
    }

    private function employeeFor(Beneficiary $beneficiary): ?Employee
    {
        $beneficiary->loadMissing('medicalRegistration.employee');

        $registration = $beneficiary->medicalRegistration
            ?? MedicalRegistration::query()->find($beneficiary->medical_registration_id);

        return $registration?->employee;
    }
}
