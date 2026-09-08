<?php

namespace App\Console\Commands;

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Services\InsuranceCardNumberAssigner;
use App\Support\InsuranceCardNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('insurance-cards:assign-numbers')]
#[Description('Assign 8-digit insurance card numbers to employees and their registered family members')]
class AssignInsuranceCardNumbersCommand extends Command
{
    public function handle(InsuranceCardNumberAssigner $assigner): int
    {
        $employeesAssigned = 0;
        $beneficiariesAssigned = 0;
        $beneficiariesSkipped = 0;

        Employee::query()
            ->whereNull('card_number')
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($assigner, &$employeesAssigned): void {
                foreach ($employees as $employee) {
                    $assigner->ensureEmployee($employee);
                    $employeesAssigned++;
                }
            });

        Beneficiary::query()
            ->whereNull('card_number')
            ->with('medicalRegistration.employee')
            ->orderBy('id')
            ->chunkById(100, function ($beneficiaries) use ($assigner, &$beneficiariesAssigned, &$beneficiariesSkipped): void {
                foreach ($beneficiaries as $beneficiary) {
                    $assigner->ensureBeneficiary($beneficiary);

                    if (InsuranceCardNumber::isValid($beneficiary->card_number)) {
                        $beneficiariesAssigned++;

                        continue;
                    }

                    $beneficiariesSkipped++;
                }
            });

        $this->info("Assigned {$employeesAssigned} employee card numbers and {$beneficiariesAssigned} family card numbers.");

        if ($beneficiariesSkipped > 0) {
            $this->warn("Skipped {$beneficiariesSkipped} family members without an employee.");
        }

        return self::SUCCESS;
    }
}
