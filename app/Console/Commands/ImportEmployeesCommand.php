<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\WorkplaceOptions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

#[Signature('employees:import {path? : Path to the employees xlsx file}')]
#[Description('Import active employees from the official staff spreadsheet')]
class ImportEmployeesCommand extends Command
{
    public function handle(): int
    {
        $path = $this->argument('path') ?: database_path('data/employees.xlsx');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($path);
        $imported = 0;
        $skipped = 0;
        $seenNumbers = [];

        DB::transaction(function () use ($spreadsheet, &$imported, &$skipped, &$seenNumbers): void {
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $title = trim((string) $worksheet->getTitle());
                $isHeadquarters = str_contains($title, 'الإدارة العامة');
                $isBranches = str_contains($title, 'الفروع');

                if (! $isHeadquarters && ! $isBranches) {
                    continue;
                }

                $highestRow = $worksheet->getHighestDataRow();

                for ($row = 4; $row <= $highestRow; $row++) {
                    $fullName = trim((string) $worksheet->getCell("B{$row}")->getValue());
                    $location = trim((string) $worksheet->getCell("C{$row}")->getValue());
                    $nationalId = trim((string) $worksheet->getCell("D{$row}")->getCalculatedValue());
                    $employeeNumber = trim((string) $worksheet->getCell("E{$row}")->getCalculatedValue());

                    if ($fullName === '' || $nationalId === '' || $employeeNumber === '') {
                        $skipped++;

                        continue;
                    }

                    $workplaceLabel = $isHeadquarters ? 'الإدارة العامة' : $location;
                    $workplaceKey = WorkplaceOptions::keyForLabel($workplaceLabel);

                    if ($workplaceKey === null) {
                        $this->warn("Unknown workplace «{$workplaceLabel}» for employee {$employeeNumber}");
                        $skipped++;

                        continue;
                    }

                    if (isset($seenNumbers[$employeeNumber])) {
                        $this->warn("Duplicate employee number {$employeeNumber} — keeping first occurrence");
                        $skipped++;

                        continue;
                    }

                    $seenNumbers[$employeeNumber] = true;

                    Employee::query()->updateOrCreate(
                        ['employee_number' => $employeeNumber],
                        [
                            'national_id' => $nationalId,
                            'full_name' => $fullName,
                            'workplace' => $workplaceKey,
                            'date_of_birth' => null,
                            'is_active' => true,
                        ],
                    );

                    $imported++;
                }
            }
        });

        $this->info("Imported {$imported} employees ({$skipped} skipped).");

        return self::SUCCESS;
    }
}
