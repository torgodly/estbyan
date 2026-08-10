<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\TestEmployees;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('employees:seed-test')]
#[Description('Create the two fixed employees used for registration testing')]
class SeedTestEmployeesCommand extends Command
{
    public function handle(): int
    {
        foreach (TestEmployees::definitions() as $definition) {
            $employee = Employee::query()->updateOrCreate(
                ['employee_number' => $definition['employee_number']],
                [
                    'national_id' => $definition['national_id'],
                    'full_name' => $definition['full_name'],
                    'workplace' => 'general_admin',
                    'date_of_birth' => null,
                    'is_active' => true,
                ],
            );

            $this->info(sprintf(
                '%s employee #%s (%s)',
                $employee->wasRecentlyCreated ? 'Created' : 'Updated',
                $employee->employee_number,
                $employee->full_name,
            ));
        }

        $this->components->success('Test employees are ready.');

        return self::SUCCESS;
    }
}
