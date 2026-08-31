<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('employees:fix-issam-qashut')]
#[Description('Update عصام المبروك انطاط قشوط national ID and full name')]
class FixIssamQashutEmployeeCommand extends Command
{
    private const EMPLOYEE_NUMBER = '028104';

    private const NATIONAL_ID = '119750300015';

    private const FULL_NAME = 'عصام المبروك انطاط قشوط';

    public function handle(): int
    {
        $employee = Employee::query()
            ->where('employee_number', self::EMPLOYEE_NUMBER)
            ->first();

        if ($employee === null) {
            $this->error('Employee #'.self::EMPLOYEE_NUMBER.' not found.');

            return self::FAILURE;
        }

        $before = [
            'full_name' => (string) $employee->full_name,
            'national_id' => (string) $employee->national_id,
        ];

        $conflict = Employee::query()
            ->where('national_id', self::NATIONAL_ID)
            ->where('employee_number', '!=', self::EMPLOYEE_NUMBER)
            ->exists();

        if ($conflict) {
            $this->error('National ID '.self::NATIONAL_ID.' already belongs to another employee.');

            return self::FAILURE;
        }

        $employee->update([
            'national_id' => self::NATIONAL_ID,
            'full_name' => self::FULL_NAME,
            'is_active' => true,
        ]);

        $this->info('UPDATED #'.self::EMPLOYEE_NUMBER);
        $this->line("  full_name: {$before['full_name']}  →  ".self::FULL_NAME);
        $this->line("  national_id: {$before['national_id']}  →  ".self::NATIONAL_ID);

        return self::SUCCESS;
    }
}
