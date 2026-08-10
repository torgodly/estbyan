<?php

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

it('lists employees and supports search', function () {
    $admin = User::factory()->create();

    $target = Employee::factory()->create([
        'full_name' => 'خالد المستهدف',
        'employee_number' => '77881',
        'national_id' => '1199001000123',
    ]);
    Employee::factory()->create([
        'full_name' => 'موظف آخر',
        'employee_number' => '11002',
    ]);

    $this->actingAs($admin);

    Livewire::test(ListEmployees::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$target])
        ->searchTable('77881')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords(
            Employee::query()->where('employee_number', '11002')->get()
        );
});

it('shows the employee dossier with registration history', function () {
    $admin = User::factory()->create();
    $employee = Employee::factory()->create([
        'full_name' => 'نادية الملف',
        'employee_number' => '33445',
    ]);
    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'full_name' => $employee->full_name,
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'reference_number' => 'SC26-12345',
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('نادية الملف')
        ->assertSee('33445')
        ->assertSee('سجل طلبات التسجيل')
        ->assertSee('SC26-12345')
        ->assertSee('بانتظار المراجعة')
        ->assertSee('فتح الملف');

    expect($registration->employee_id)->toBe($employee->id);
});
