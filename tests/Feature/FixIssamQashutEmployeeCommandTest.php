<?php

use App\Models\Employee;
use Illuminate\Support\Facades\Artisan;

it('updates issam qashut national id and full name', function () {
    Employee::factory()->create([
        'employee_number' => '028104',
        'national_id' => '11750300015',
        'full_name' => 'عصام المبروك قشوط',
        'workplace' => 'gharyan',
        'is_active' => true,
    ]);

    $exitCode = Artisan::call('employees:fix-issam-qashut');

    $employee = Employee::query()->where('employee_number', '028104')->first();

    expect($exitCode)->toBe(0)
        ->and($employee->national_id)->toBe('119750300015')
        ->and($employee->full_name)->toBe('عصام المبروك انطاط قشوط')
        ->and($employee->workplace)->toBe('gharyan')
        ->and($employee->is_active)->toBeTrue();

    $output = Artisan::output();

    expect($output)->toContain('UPDATED #028104')
        ->and($output)->toContain('national_id: 11750300015  →  119750300015')
        ->and($output)->toContain('full_name: عصام المبروك قشوط  →  عصام المبروك انطاط قشوط');
});

it('fails when employee 028104 does not exist', function () {
    $exitCode = Artisan::call('employees:fix-issam-qashut');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Employee #028104 not found.');
});
