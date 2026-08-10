<?php

use App\Models\Employee;
use Illuminate\Support\Facades\Artisan;

it('imports employees from the bundled spreadsheet', function () {
    $path = database_path('data/employees.xlsx');

    expect(is_file($path))->toBeTrue();

    Artisan::call('employees:import', ['path' => $path]);

    expect(Employee::query()->count())->toBeGreaterThan(1000)
        ->and(Employee::query()->where('workplace', 'general_admin')->exists())->toBeTrue()
        ->and(Employee::query()->where('workplace', 'sebha')->exists())->toBeTrue()
        ->and(Employee::query()->whereNotNull('date_of_birth')->count())->toBe(0);
});
