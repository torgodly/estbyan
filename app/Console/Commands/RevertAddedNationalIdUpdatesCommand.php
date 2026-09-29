<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\EmployeeNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('employees:revert-added-national-id-updates')]
#[Description('Restore the eight employees overwritten by employees:import-added-national-ids')]
class RevertAddedNationalIdUpdatesCommand extends Command
{
    public function handle(): int
    {
        $reverted = 0;
        $missing = 0;

        foreach (self::restorations() as $restoration) {
            $employee = Employee::query()
                ->where('employee_number', $restoration['employee_number'])
                ->first();

            if ($employee === null) {
                $this->warn("Missing employee #{$restoration['employee_number']} — skipped");
                $missing++;

                continue;
            }

            $employee->fill($restoration['restore']);
            $employee->save();

            $this->line("  #{$restoration['employee_number']} restored");
            $reverted++;
        }

        $this->newLine();
        $this->components->success(sprintf(
            'Restored %d employee%s%s.',
            $reverted,
            $reverted === 1 ? '' : 's',
            $missing > 0 ? ", {$missing} missing" : '',
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<array{employee_number: string, restore: array<string, mixed>}>
     */
    public static function restorations(): array
    {
        return [
            [
                'employee_number' => EmployeeNumber::normalize('012286'),
                'restore' => [
                    'full_name' => 'عبدالله طارق عبدالله بوشعاله',
                ],
            ],
            [
                'employee_number' => EmployeeNumber::normalize('006236'),
                'restore' => [
                    'full_name' => 'حمزة احمد سالم البربار',
                    'workplace' => 'sorman',
                ],
            ],
            [
                'employee_number' => EmployeeNumber::normalize('040039'),
                'restore' => [
                    'full_name' => 'ريان محمد عبدالسلام الشريدي',
                ],
            ],
            [
                'employee_number' => EmployeeNumber::normalize('022137'),
                'restore' => [
                    'full_name' => 'رؤي عبدالله ابراهيم تصر',
                ],
            ],
            [
                'employee_number' => EmployeeNumber::normalize('006243'),
                'restore' => [
                    'full_name' => 'عبدالعزيز عبدالسلام احمد شبانة',
                    'office' => 'ابو عيسى',
                ],
            ],
            [
                'employee_number' => EmployeeNumber::normalize('006245'),
                'restore' => [
                    'full_name' => 'احمد علي عيسي المحروق',
                    'office' => 'ابو عيسى',
                ],
            ],
            [
                'employee_number' => EmployeeNumber::normalize('000426'),
                'restore' => [
                    'full_name' => 'رندة سالم اوحيدة الطبولي',
                    'workplace' => null,
                    'office' => null,
                ],
            ],
            [
                'employee_number' => EmployeeNumber::normalize('040123'),
                'restore' => [
                    'full_name' => 'فيصل نوري المسماري',
                ],
            ],
        ];
    }
}
