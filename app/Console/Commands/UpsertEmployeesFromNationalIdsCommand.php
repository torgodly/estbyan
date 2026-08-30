<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

#[Signature('employees:upsert-national-ids')]
#[Description('Create or update employees from database/data/national-ids.xlsx')]
class UpsertEmployeesFromNationalIdsCommand extends Command
{
    public function handle(): int
    {
        $path = database_path('data/national-ids.xlsx');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($path);
        $createdRows = [];
        $updatedRows = [];
        $unchanged = 0;
        $skipped = 0;
        $seenNumbers = [];

        DB::transaction(function () use ($spreadsheet, &$createdRows, &$updatedRows, &$unchanged, &$skipped, &$seenNumbers): void {
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $highestRow = $worksheet->getHighestDataRow();
                $startRow = $this->firstDataRow($worksheet);

                for ($row = $startRow; $row <= $highestRow; $row++) {
                    $rawNumber = $this->cellString($worksheet, "A{$row}");
                    $fullName = $this->cellString($worksheet, "B{$row}");
                    $nationalId = $this->cellString($worksheet, "C{$row}");

                    if ($rawNumber === '' && $fullName === '' && $nationalId === '') {
                        continue;
                    }

                    if ($rawNumber === '' || $fullName === '' || $nationalId === '') {
                        $this->warn("Row {$row}: missing employee number, name, or national ID — skipped");
                        $skipped++;

                        continue;
                    }

                    if (! ctype_digit($rawNumber)) {
                        $this->warn("Row {$row}: invalid employee number «{$rawNumber}» — skipped");
                        $skipped++;

                        continue;
                    }

                    $employeeNumber = str_pad($rawNumber, 6, '0', STR_PAD_LEFT);

                    if (isset($seenNumbers[$employeeNumber])) {
                        $this->warn("Row {$row}: duplicate employee number {$employeeNumber} — skipped");
                        $skipped++;

                        continue;
                    }

                    $seenNumbers[$employeeNumber] = true;

                    $conflict = Employee::query()
                        ->where('national_id', $nationalId)
                        ->where('employee_number', '!=', $employeeNumber)
                        ->first();

                    if ($conflict !== null) {
                        $this->warn(
                            "Row {$row}: national ID {$nationalId} already belongs to employee {$conflict->employee_number} — skipped",
                        );
                        $skipped++;

                        continue;
                    }

                    $employee = Employee::query()->firstOrNew(
                        ['employee_number' => $employeeNumber],
                    );

                    if (! $employee->exists) {
                        $employee->fill([
                            'national_id' => $nationalId,
                            'full_name' => $fullName,
                            'is_active' => true,
                            'date_of_birth' => null,
                        ]);
                        $employee->save();

                        $createdRows[] = [
                            'employee_number' => $employeeNumber,
                            'full_name' => $fullName,
                            'national_id' => $nationalId,
                        ];

                        continue;
                    }

                    $before = [
                        'full_name' => (string) $employee->full_name,
                        'national_id' => (string) $employee->national_id,
                        'is_active' => $employee->is_active ? '1' : '0',
                    ];

                    $after = [
                        'full_name' => $fullName,
                        'national_id' => $nationalId,
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
                        'national_id' => $nationalId,
                        'full_name' => $fullName,
                        'is_active' => true,
                    ]);
                    $employee->save();

                    $updatedRows[] = [
                        'employee_number' => $employeeNumber,
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

    private function firstDataRow(Worksheet $worksheet): int
    {
        $firstNumber = $this->cellString($worksheet, 'A1');

        if ($firstNumber !== '' && ! ctype_digit($firstNumber)) {
            return 2;
        }

        return 1;
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
