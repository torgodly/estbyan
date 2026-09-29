<?php

use App\Models\Employee;
use App\Support\WorkplaceOptions;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('creates employees with a national id and skips anyone without one', function () {
    $existing = Employee::factory()->create([
        'employee_number' => '003100',
        'national_id' => '219980115093',
        'full_name' => 'اسم قديم',
        'workplace' => 'sebha',
        'office' => 'مكتب قديم',
        'date_of_birth' => '1998-01-01',
        'is_active' => false,
    ]);
    $untouched = Employee::factory()->create([
        'employee_number' => '001000',
        'is_active' => true,
    ]);

    $path = addedNationalIdsSpreadsheetPath([
        ['القرار 368', 'الإدارة التابع لها', 'الرقم الوطني'],
        ['اســــــــم الموظـــــــــــف', '', ''],
        ['خديجة فوزي عيسى الاوجلي', 'اجدابيا', '219980115093'],
        ['جمال عبدالباسط حسين الجمالي', 'بنغازي', ''],
        ['سارة جمعة علي الفلاح', 'بنغازي', '219920396012'],
        ['وسام عبدالسلام سليمان بوضاوي', 'العامة /الحركة ونقل', '119880112233'],
        ['عماد عبدالله الاطرش', 'طرابلس', '19950450284'],
        ['شخص بمكان مجهول', 'مكان غير موجود', '119880045493'],
    ]);

    Artisan::call('employees:import-added-national-ids', ['path' => $path]);

    $existing->refresh();
    $createdBenghazi = Employee::query()->where('national_id', '219920396012')->first();
    $createdAdmin = Employee::query()->where('national_id', '119880112233')->first();

    expect($existing->full_name)->toBe('خديجة فوزي عيسى الاوجلي')
        ->and($existing->workplace)->toBe('ajdabiya')
        ->and($existing->office)->toBeNull()
        ->and($existing->employee_number)->toBe('003100')
        ->and($existing->date_of_birth?->format('Y-m-d'))->toBe('1998-01-01')
        ->and($existing->is_active)->toBeTrue()
        ->and($createdBenghazi)->not->toBeNull()
        ->and($createdBenghazi->full_name)->toBe('سارة جمعة علي الفلاح')
        ->and($createdBenghazi->workplace)->toBe('benghazi')
        ->and($createdBenghazi->employee_number)->toBe('003101')
        ->and($createdAdmin)->not->toBeNull()
        ->and($createdAdmin->workplace)->toBe('general_admin')
        ->and($createdAdmin->office)->toBe('الحركة ونقل')
        ->and($untouched->fresh()->is_active)->toBeTrue()
        ->and(Employee::query()->where('full_name', 'جمال عبدالباسط حسين الجمالي')->exists())->toBeFalse()
        ->and(Employee::query()->where('national_id', '19950450284')->exists())->toBeFalse()
        ->and(Employee::query()->where('national_id', '119880045493')->exists())->toBeFalse();

    $output = Artisan::output();

    expect($output)->toContain('UPDATED (1)')
        ->and($output)->toContain('#003100')
        ->and($output)->toContain('CREATED (2)')
        ->and($output)->toContain('missing national ID')
        ->and($output)->toContain('invalid national ID')
        ->and($output)->toContain('Summary: created 2, updated 1, unchanged 0, skipped 3.');
});

it('fails when the added national ids spreadsheet is missing', function () {
    $exitCode = Artisan::call('employees:import-added-national-ids', [
        'path' => storage_path('framework/missing-added-national-ids.xlsx'),
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('File not found');
});

it('maps every workplace in the added national ids spreadsheet', function () {
    $path = database_path('data/added-national-ids.xlsx');

    expect(is_file($path))->toBeTrue();

    $spreadsheet = IOFactory::load($path);
    $unknown = [];

    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
        $highestRow = $worksheet->getHighestDataRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $name = trim((string) $worksheet->getCell("A{$row}")->getFormattedValue());
            $admin = trim((string) $worksheet->getCell("B{$row}")->getFormattedValue());

            if ($admin === '' || str_starts_with($name, 'القرار') || str_contains($admin, 'الإدارة التابع')) {
                continue;
            }

            if (WorkplaceOptions::parseSpreadsheetAdmin($admin) === null) {
                $unknown[$admin] = true;
            }
        }
    }

    expect($unknown)->toBeEmpty();
});

it('imports the real added national ids spreadsheet and skips rows without a nid', function () {
    $path = database_path('data/added-national-ids.xlsx');

    expect(is_file($path))->toBeTrue();

    $other = Employee::factory()->create([
        'employee_number' => '001000',
        'is_active' => true,
    ]);

    Artisan::call('employees:import-added-national-ids', ['path' => $path]);

    $imported = Employee::query()->where('national_id', '219980115093')->first();

    expect($imported)->not->toBeNull()
        ->and($imported->full_name)->toBe('خديجة فوزي عيسى الاوجلي')
        ->and($imported->workplace)->toBe('ajdabiya')
        ->and($imported->is_active)->toBeTrue()
        ->and(Employee::query()->where('full_name', 'جمال عبدالباسط حسين الجمالي')->exists())->toBeFalse()
        ->and($other->fresh()->is_active)->toBeTrue()
        ->and(Employee::query()->where('employee_number', '!=', '001000')->count())->toBe(86)
        ->and(Artisan::output())->toContain('Summary: created 86, updated 0, unchanged 0, skipped 19.');
});

/**
 * @param  list<list<string>>  $rows
 */
function addedNationalIdsSpreadsheetPath(array $rows): string
{
    $path = sys_get_temp_dir().'/added-national-ids-test.xlsx';

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}
