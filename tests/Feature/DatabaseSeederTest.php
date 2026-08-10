<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\SupportAdminSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('seeds a support admin and prints the hard password', function () {
    Artisan::call('db:seed', ['--class' => SupportAdminSeeder::class]);
    $output = Artisan::output();

    $admin = User::query()->where('email', SupportAdminSeeder::EMAIL)->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Support Admin')
        ->and(Hash::check(SupportAdminSeeder::PASSWORD, $admin->password))->toBeTrue()
        ->and($output)->toContain(SupportAdminSeeder::EMAIL)
        ->and($output)->toContain(SupportAdminSeeder::PASSWORD);
});

it('seeds employees from the bundled spreadsheet', function () {
    $this->seed(EmployeeSeeder::class);

    expect(Employee::query()->count())->toBeGreaterThan(1000)
        ->and(Employee::query()->where('workplace', 'general_admin')->exists())->toBeTrue();
});
