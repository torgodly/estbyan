<?php

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

it('does not allow creating or editing employees from the admin panel', function () {
    $employee = Employee::factory()->create();

    expect(EmployeeResource::canCreate())->toBeFalse()
        ->and(EmployeeResource::canEdit($employee))->toBeFalse()
        ->and(EmployeeResource::getPages())->not->toHaveKeys(['create', 'edit']);
});

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

it('filters employees who submitted the form versus those who have not', function () {
    $admin = User::factory()->create();

    $submittedEmployee = Employee::factory()->create([
        'full_name' => 'موظف مرسل',
    ]);
    $notSubmittedEmployee = Employee::factory()->create([
        'full_name' => 'موظف لم يرسل',
    ]);
    $draftOnlyEmployee = Employee::factory()->create([
        'full_name' => 'موظف مسودة فقط',
    ]);

    MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $submittedEmployee->id,
        'full_name' => $submittedEmployee->full_name,
        'employee_number' => $submittedEmployee->employee_number,
        'national_id' => $submittedEmployee->national_id,
    ]);
    MedicalRegistration::factory()->create([
        'employee_id' => $draftOnlyEmployee->id,
        'full_name' => $draftOnlyEmployee->full_name,
        'employee_number' => $draftOnlyEmployee->employee_number,
        'national_id' => $draftOnlyEmployee->national_id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListEmployees::class)
        ->assertSuccessful()
        ->assertSee('أرسلوا النموذج')
        ->assertSee('لم يرسلوا')
        ->assertSee('أرسل النموذج')
        ->assertSee('لم يرسل')
        ->set('activeTab', 'submitted')
        ->assertCanSeeTableRecords([$submittedEmployee])
        ->assertCanNotSeeTableRecords([$notSubmittedEmployee, $draftOnlyEmployee])
        ->set('activeTab', 'not_submitted')
        ->assertCanSeeTableRecords([$notSubmittedEmployee, $draftOnlyEmployee])
        ->assertCanNotSeeTableRecords([$submittedEmployee]);
});

it('shows the employee dossier with registration history and submission state', function () {
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
        ->assertSee('أرسل النموذج')
        ->assertSee('سجل طلبات التسجيل')
        ->assertSee('SC26-12345')
        ->assertSee('فتح الملف');

    expect($registration->employee_id)->toBe($employee->id)
        ->and($employee->fresh()->hasSubmittedForm())->toBeTrue();
});

it('shows an empty state when the employee has not submitted', function () {
    $admin = User::factory()->create();
    $employee = Employee::factory()->create([
        'full_name' => 'موظف بلا طلب',
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('لم يرسل النموذج')
        ->assertSee('هذا الموظف لم يرسل النموذج بعد')
        ->assertSee('لا توجد طلبات لهذا الموظف');
});
