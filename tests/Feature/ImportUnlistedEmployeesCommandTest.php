<?php

use App\Models\Employee;
use App\Support\WorkplaceOptions;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('creates and updates unlisted employees from the decisions spreadsheet', function () {
    $existing = Employee::factory()->create([
        'employee_number' => '002828',
        'national_id' => '119940346272',
        'full_name' => 'اسم قديم',
        'workplace' => 'sebha',
        'office' => 'مكتب قديم',
        'date_of_birth' => '1994-01-01',
        'is_active' => false,
    ]);
    $untouched = Employee::factory()->create([
        'employee_number' => '001000',
        'is_active' => true,
    ]);

    $path = unlistedSpreadsheetPath([
        ['اسم الموظف', 'الإدارة التابع لها', 'الرقم الوطني'],
        ['مهند جمعة مبروك المقرحي', 'طرابلس', '119940346272'],
        ['نورة مرعي يونس الصديق', 'الشويرف', '220050280605'],
        ['أيوب يوسف عبد السلام قشوط', 'العامة /الخدمات', '119940579409'],
        ['خديجة فوزي عيسى الاوجلي', 'اجدابيا', ''],
        ['اينارو دياب مسعود اعشيني', 'زوارة', '20030604757'],
        ['شخص بمكان مجهول', 'مكان غير موجود', '119880045493'],
    ]);

    Artisan::call('employees:import-unlisted', ['path' => $path]);

    $existing->refresh();
    $createdShuwayrif = Employee::query()->where('national_id', '220050280605')->first();
    $createdAdmin = Employee::query()->where('national_id', '119940579409')->first();

    expect($existing->full_name)->toBe('مهند جمعة مبروك المقرحي')
        ->and($existing->workplace)->toBe('tripoli')
        ->and($existing->office)->toBeNull()
        ->and($existing->employee_number)->toBe('002828')
        ->and($existing->date_of_birth?->format('Y-m-d'))->toBe('1994-01-01')
        ->and($existing->is_active)->toBeTrue()
        ->and($createdShuwayrif)->not->toBeNull()
        ->and($createdShuwayrif->full_name)->toBe('نورة مرعي يونس الصديق')
        ->and($createdShuwayrif->workplace)->toBe('al_shuwayrif')
        ->and($createdShuwayrif->employee_number)->toBe('002829')
        ->and($createdShuwayrif->is_active)->toBeTrue()
        ->and($createdAdmin)->not->toBeNull()
        ->and($createdAdmin->workplace)->toBe('general_admin')
        ->and($createdAdmin->office)->toBe('الخدمات')
        ->and($createdAdmin->employee_number)->toBe('002830')
        ->and($untouched->fresh()->is_active)->toBeTrue()
        ->and(Employee::query()->where('national_id', '119880045493')->exists())->toBeFalse();

    $output = Artisan::output();

    expect($output)->toContain('UPDATED (1)')
        ->and($output)->toContain('#002828')
        ->and($output)->toContain('CREATED (2)')
        ->and($output)->toContain('Summary: created 2, updated 1, unchanged 0, skipped 3.');
});

it('fails when the unlisted employees spreadsheet is missing', function () {
    $exitCode = Artisan::call('employees:import-unlisted', [
        'path' => storage_path('framework/missing-unlisted-employees.xlsx'),
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('File not found');
});

it('maps every workplace in the unlisted employees spreadsheet', function () {
    $path = database_path('data/unlisted-employees.xlsx');

    expect(is_file($path))->toBeTrue();

    $spreadsheet = IOFactory::load($path);
    $unknown = [];

    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
        $highestRow = $worksheet->getHighestDataRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $name = trim((string) $worksheet->getCell("A{$row}")->getFormattedValue());
            $admin = trim((string) $worksheet->getCell("B{$row}")->getFormattedValue());

            if ($admin === '' || $name === 'اسم الموظف' || str_starts_with($name, 'القرار') || str_contains($admin, 'الإدارة التابع')) {
                continue;
            }

            if (WorkplaceOptions::parseSpreadsheetAdmin($admin) === null) {
                $unknown[$admin] = true;
            }
        }
    }

    expect($unknown)->toBeEmpty();
});

it('imports the real unlisted employees spreadsheet without deactivating others', function () {
    $path = database_path('data/unlisted-employees.xlsx');

    expect(is_file($path))->toBeTrue();

    $other = Employee::factory()->create([
        'employee_number' => '001000',
        'is_active' => true,
    ]);

    Employee::factory()->create([
        'employee_number' => '002828',
        'national_id' => '119940346272',
        'full_name' => 'مهند جمعه المبروك المقرحي',
        'workplace' => 'tripoli',
        'date_of_birth' => '1994-03-01',
        'is_active' => true,
    ]);

    Artisan::call('employees:import-unlisted', ['path' => $path]);

    $imported = Employee::query()->where('national_id', '119950469905')->first();
    $updated = Employee::query()->where('national_id', '119940346272')->first();

    expect($imported)->not->toBeNull()
        ->and($imported->full_name)->toBe('مصطفى رجب محمد التاورغي')
        ->and($imported->workplace)->toBe('benghazi')
        ->and($imported->is_active)->toBeTrue()
        ->and($updated->full_name)->toBe('مهند جمعة مبروك المقرحي')
        ->and($updated->employee_number)->toBe('002828')
        ->and($updated->date_of_birth?->format('Y-m-d'))->toBe('1994-03-01')
        ->and(Employee::query()->where('workplace', 'al_shuwayrif')->count())->toBeGreaterThan(0)
        ->and(Employee::query()->where('workplace', 'general_admin')->whereNotNull('office')->count())->toBeGreaterThan(0)
        ->and($other->fresh()->is_active)->toBeTrue()
        ->and(Employee::query()->count())->toBeGreaterThan(150)
        ->and(Artisan::output())->toContain('Summary: created');
});

/**
 * @param  list<list<string>>  $rows
 */
function unlistedSpreadsheetPath(array $rows): string
{
    $path = sys_get_temp_dir().'/unlisted-employees-test.xlsx';

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}
