<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\EmployeeNumber;
use App\Support\LibyanNationalId;
use App\Support\WorkplaceOptions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

#[Signature('employees:import-unlisted {path? : Path to the unlisted employees xlsx file}')]
#[Description('Import employees left out of the system (decisions 386/368 and 116)')]
class ImportUnlistedEmployeesCommand extends Command
{
    public function handle(): int
    {
        $path = $this->argument('path') ?: database_path('data/unlisted-employees.xlsx');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($path);
        $createdRows = [];
        $updatedRows = [];
        $unchanged = 0;
        $skipped = 0;
        $seenNationalIds = [];

        DB::transaction(function () use ($spreadsheet, &$createdRows, &$updatedRows, &$unchanged, &$skipped, &$seenNationalIds): void {
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $highestRow = $worksheet->getHighestDataRow();

                for ($row = 1; $row <= $highestRow; $row++) {
                    $fullName = $this->employeeName($this->cellString($worksheet, "A{$row}"));
                    $admin = $this->cellString($worksheet, "B{$row}");
                    $nationalId = $this->nationalId($this->cellString($worksheet, "C{$row}"));

                    if ($fullName === '' && $admin === '' && $nationalId === '') {
                        continue;
                    }

                    if ($this->isHeaderRow($fullName, $admin, $nationalId)) {
                        continue;
                    }

                    $sheetRow = $worksheet->getTitle().':'.$row;

                    if ($fullName === '') {
                        $this->warn("{$sheetRow}: missing name — skipped");
                        $skipped++;

                        continue;
                    }

                    if ($nationalId === '') {
                        $this->warn("{$sheetRow}: missing national ID for «{$fullName}» — skipped");
                        $skipped++;

                        continue;
                    }

                    if (! LibyanNationalId::isValid($nationalId)) {
                        $this->warn("{$sheetRow}: invalid national ID «{$nationalId}» for «{$fullName}» — skipped");
                        $skipped++;

                        continue;
                    }

                    if (isset($seenNationalIds[$nationalId])) {
                        $this->warn("{$sheetRow}: duplicate national ID {$nationalId} — skipped");
                        $skipped++;

                        continue;
                    }

                    $parsedAdmin = WorkplaceOptions::parseSpreadsheetAdmin($admin);

                    if ($parsedAdmin === null) {
                        $this->warn("{$sheetRow}: unknown workplace «{$admin}» for «{$fullName}» — skipped");
                        $skipped++;

                        continue;
                    }

                    $seenNationalIds[$nationalId] = true;

                    $employee = Employee::query()->firstOrNew(
                        ['national_id' => $nationalId],
                    );

                    if (! $employee->exists) {
                        $employee->fill([
                            'employee_number' => EmployeeNumber::next(),
                            'national_id' => $nationalId,
                            'full_name' => $fullName,
                            'workplace' => $parsedAdmin['workplace'],
                            'office' => $parsedAdmin['office'],
                            'date_of_birth' => null,
                            'is_active' => true,
                        ]);
                        $employee->save();

                        $createdRows[] = [
                            'employee_number' => $employee->employee_number,
                            'full_name' => $fullName,
                            'national_id' => $nationalId,
                        ];

                        continue;
                    }

                    $before = [
                        'full_name' => (string) $employee->full_name,
                        'workplace' => (string) $employee->workplace,
                        'office' => (string) $employee->office,
                        'is_active' => $employee->is_active ? '1' : '0',
                    ];

                    $after = [
                        'full_name' => $fullName,
                        'workplace' => $parsedAdmin['workplace'],
                        'office' => (string) $parsedAdmin['office'],
                        'is_active' => '1',
                    ];

                    $changes = [];

                    foreach ($after as $field => $newValue) {
                        if ($before[$field] !== $newValue) {
                            $changes[$field] = [
                                'from' => $before[$field],
                                'to' => $newValue,
                            ];
                        }
                    }

                    if ($changes === []) {
                        $unchanged++;

                        continue;
                    }

                    $employee->fill([
                        'full_name' => $fullName,
                        'workplace' => $parsedAdmin['workplace'],
                        'office' => $parsedAdmin['office'],
                        'is_active' => true,
                    ]);
                    $employee->save();

                    $updatedRows[] = [
                        'employee_number' => $employee->employee_number,
                        'changes' => $changes,
                    ];
                }
            }
        });

        $this->printReport($createdRows, $updatedRows, $unchanged, $skipped);

        return self::SUCCESS;
    }

    /**
     * @param  list<array{employee_number: string, full_name: string, national_id: string}>  $createdRows
     * @param  list<array{employee_number: string, changes: array<string, array{from: string, to: string}>}>  $updatedRows
     */
    private function printReport(array $createdRows, array $updatedRows, int $unchanged, int $skipped): void
    {
        if ($updatedRows !== []) {
            $this->newLine();
            $this->info('UPDATED ('.count($updatedRows).')');

            foreach ($updatedRows as $row) {
                $this->line("  #{$row['employee_number']}");

                foreach ($row['changes'] as $field => $change) {
                    $this->line("    {$field}: {$change['from']}  →  {$change['to']}");
                }
            }
        }

        if ($createdRows !== []) {
            $this->newLine();
            $this->info('CREATED ('.count($createdRows).')');

            foreach ($createdRows as $row) {
                $this->line("  #{$row['employee_number']} | {$row['national_id']} | {$row['full_name']}");
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Summary: created %d, updated %d, unchanged %d, skipped %d.',
            count($createdRows),
            count($updatedRows),
            $unchanged,
            $skipped,
        ));
    }

    private function isHeaderRow(string $name, string $admin, string $nationalId): bool
    {
        return $name === 'اسم الموظف'
            || str_starts_with($name, 'القرار')
            || str_contains($admin, 'الإدارة التابع')
            || str_contains($admin, 'الادارة التابع')
            || $nationalId === 'الرقم الوطني';
    }

    private function employeeName(string $raw): string
    {
        $value = WorkplaceOptions::cleanSpreadsheetAdmin($raw);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function nationalId(string $raw): string
    {
        return preg_replace('/\s+/u', '', $raw) ?? $raw;
    }

    private function cellString(Worksheet $worksheet, string $coordinate): string
    {
        $value = $worksheet->getCell($coordinate)->getFormattedValue();

        if ($value === null) {
            $value = $worksheet->getCell($coordinate)->getCalculatedValue();
        }

        return trim((string) $value);
    }
}
