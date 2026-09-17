<?php

namespace App\Console\Commands;

use App\Support\DuplicateNationalIdsReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('employees:report-duplicate-nids')]
#[Description('Report national IDs reused across employees and beneficiaries (read-only)')]
class ReportDuplicateNationalIdsCommand extends Command
{
    public function handle(): int
    {
        $report = DuplicateNationalIdsReport::build();

        $employeeAsBeneficiary = $report['employee_as_beneficiary'];
        $duplicateBeneficiaries = $report['duplicate_beneficiaries'];
        $duplicateEmployees = $report['duplicate_employees'];

        $this->components->info('Duplicate national IDs (report only — nothing was changed)');
        $this->newLine();

        $this->components->twoColumnDetail(
            'Employee NIDs added as someone else\'s beneficiary',
            (string) count($employeeAsBeneficiary),
        );
        $this->components->twoColumnDetail(
            'Beneficiary NIDs used on more than one registration',
            (string) count($duplicateBeneficiaries),
        );
        $this->components->twoColumnDetail(
            'Employee NIDs appearing on more than one employee record',
            (string) count($duplicateEmployees),
        );
        $this->newLine();

        if ($employeeAsBeneficiary !== []) {
            $this->components->info('Employees who appear as another employee\'s beneficiary');
            $this->table(
                ['NID', 'Employee', '#', 'Listed as', 'On registration of', '#', 'Relationship'],
                array_map(fn (array $row): array => [
                    $row['national_id'],
                    $row['employee_name'],
                    $row['employee_number'],
                    $row['beneficiary_name'],
                    $row['beneficiary_of'],
                    $row['beneficiary_of_number'],
                    $row['relationship'],
                ], $employeeAsBeneficiary),
            );
        }

        if ($duplicateBeneficiaries !== []) {
            $this->components->info('Beneficiary national IDs used more than once');

            $rows = [];

            foreach ($duplicateBeneficiaries as $group) {
                foreach ($group['appearances'] as $appearance) {
                    $rows[] = [
                        $group['national_id'],
                        (string) $group['count'],
                        $appearance['beneficiary_name'],
                        $appearance['beneficiary_of'],
                        $appearance['beneficiary_of_number'],
                        $appearance['relationship'],
                    ];
                }
            }

            $this->table(
                ['NID', 'Times', 'Beneficiary', 'On registration of', '#', 'Relationship'],
                $rows,
            );
        }

        if ($duplicateEmployees !== []) {
            $this->components->info('Employee national IDs used more than once');

            $rows = [];

            foreach ($duplicateEmployees as $group) {
                foreach ($group['employees'] as $employee) {
                    $rows[] = [
                        $group['national_id'],
                        (string) $group['count'],
                        $employee['name'],
                        $employee['employee_number'],
                    ];
                }
            }

            $this->table(
                ['NID', 'Times', 'Employee', '#'],
                $rows,
            );
        }

        if ($employeeAsBeneficiary === [] && $duplicateBeneficiaries === [] && $duplicateEmployees === []) {
            $this->components->success('No duplicated national IDs found.');
        }

        return self::SUCCESS;
    }
}
