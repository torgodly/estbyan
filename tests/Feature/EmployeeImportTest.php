<?php

use App\Models\Employee;
use Illuminate\Support\Facades\Artisan;

it('imports employees from the tax authority spreadsheet', function () {
    $path = database_path('data/employees.xlsx');

    expect(is_file($path))->toBeTrue();

    Artisan::call('employees:import', ['path' => $path]);

    expect(Employee::query()->where('is_active', true)->count())->toBeGreaterThan(4000)
        ->and(Employee::query()->where('workplace', 'general_admin')->where('is_active', true)->exists())->toBeTrue()
        ->and(Employee::query()->where('workplace', 'tripoli')->where('is_active', true)->exists())->toBeTrue()
        ->and(Employee::query()->where('workplace', 'sebha')->where('is_active', true)->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '007017')->where('national_id', '119730351644')->exists())->toBeTrue()
        ->and(Employee::query()->whereNotNull('date_of_birth')->count())->toBe(0);
});
