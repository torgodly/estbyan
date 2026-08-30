<?php

use App\Console\Commands\UpsertEmployeesFromNationalIdsCommand;
use App\Models\Employee;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Storage::disk('local')->makeDirectory('imports');
});

afterEach(function () {
    Storage::disk('local')->delete(UpsertEmployeesFromNationalIdsCommand::RELATIVE_PATH);
});

it('creates new employees and updates existing ones from the national ids spreadsheet', function () {
    Employee::factory()->create([
        'employee_number' => '001532',
        'national_id' => '22003039731',
        'full_name' => 'اسم قديم',
        'workplace' => 'tripoli',
        'office' => 'مكتب قديم',
        'date_of_birth' => '2003-01-01',
        'is_active' => false,
    ]);

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['1532', 'نصيب عبدالرؤوف ابراهيم الصغير لاغه', '220030309731'],
        ['6252', 'نادية علي مصباح خليفة', '219890263624'],
        ['12037', 'حسن احمد عبدالرحيم', ''],
    ], null, 'A1');

    (new Xlsx($spreadsheet))->save(Storage::disk('local')->path(UpsertEmployeesFromNationalIdsCommand::RELATIVE_PATH));

    Artisan::call('employees:upsert-national-ids');

    $existing = Employee::query()->where('employee_number', '001532')->first();
    $created = Employee::query()->where('employee_number', '006252')->first();

    expect($existing)->not->toBeNull()
        ->and($existing->national_id)->toBe('220030309731')
        ->and($existing->full_name)->toBe('نصيب عبدالرؤوف ابراهيم الصغير لاغه')
        ->and($existing->workplace)->toBe('tripoli')
        ->and($existing->office)->toBe('مكتب قديم')
        ->and($existing->date_of_birth?->format('Y-m-d'))->toBe('2003-01-01')
        ->and($existing->is_active)->toBeTrue()
        ->and($created)->not->toBeNull()
        ->and($created->national_id)->toBe('219890263624')
        ->and($created->full_name)->toBe('نادية علي مصباح خليفة')
        ->and($created->is_active)->toBeTrue()
        ->and(Employee::query()->where('employee_number', '012037')->exists())->toBeFalse();

    $output = Artisan::output();

    expect($output)->toContain('UPDATED (1)')
        ->and($output)->toContain('#001532')
        ->and($output)->toContain('national_id: 22003039731  →  220030309731')
        ->and($output)->toContain('full_name: اسم قديم  →  نصيب عبدالرؤوف ابراهيم الصغير لاغه')
        ->and($output)->toContain('is_active: 0  →  1')
        ->and($output)->toContain('CREATED (1)')
        ->and($output)->toContain('#006252 | 219890263624 | نادية علي مصباح خليفة')
        ->and($output)->toContain('Summary: created 1, updated 1, unchanged 0, skipped 1.');
});

it('skips rows whose national id already belongs to another employee', function () {
    Employee::factory()->create([
        'employee_number' => '001000',
        'national_id' => '119780100766',
        'full_name' => 'موظف آخر',
    ]);

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['1642', 'عبدالحكيم سالم عمار الهنشيري', '119780100766'],
    ], null, 'A1');

    (new Xlsx($spreadsheet))->save(Storage::disk('local')->path(UpsertEmployeesFromNationalIdsCommand::RELATIVE_PATH));

    Artisan::call('employees:upsert-national-ids');

    expect(Employee::query()->where('employee_number', '001642')->exists())->toBeFalse()
        ->and(Artisan::output())->toContain('Summary: created 0, updated 0, unchanged 0, skipped 1.');
});
