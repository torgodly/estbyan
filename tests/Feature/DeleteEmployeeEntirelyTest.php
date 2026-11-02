<?php

use App\Enums\BeneficiaryCancellationReason;
use App\Enums\BeneficiaryRelationship;
use App\Enums\RegistrationStatus;
use App\Enums\ReviewFlagReason;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\RegistrationReviewLog;
use App\Models\ReviewFlag;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * @return array{employee: Employee, registration: MedicalRegistration, oldRegistration: MedicalRegistration}
 */
function employeeWithEverything(): array
{
    Storage::fake('local');

    $employee = Employee::factory()->create(['employee_number' => '002876']);
    $registration = MedicalRegistration::factory()->approved()->create([
        'employee_id' => $employee->id,
        'employee_photo_path' => 'registrations/x/employee.jpg',
        'family_status_document_path' => 'registrations/x/family.pdf',
    ]);
    $oldRegistration = MedicalRegistration::factory()->create(['employee_id' => $employee->id]);
    $oldRegistration->delete();

    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Son,
        'photo_path' => 'registrations/x/son.jpg',
    ]);
    $cancelled = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $cancelled->cancel(BeneficiaryCancellationReason::EmployeeDuplicate);
    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Daughter,
    ])->delete();

    RegistrationReviewLog::factory()->create([
        'medical_registration_id' => $registration->id,
        'action' => RegistrationStatus::Approved,
    ]);
    ReviewFlag::raise($employee, ReviewFlagReason::PossibleDuplicateEmployee, 'x');

    foreach (['employee.jpg', 'family.pdf', 'son.jpg'] as $file) {
        Storage::disk('local')->put('registrations/x/'.$file, 'content');
    }

    return compact('employee', 'registration', 'oldRegistration');
}

it('lets support delete an employee with everything attached', function () {
    $this->actingAs(User::factory()->smartCare()->create());
    ['employee' => $employee, 'registration' => $registration, 'oldRegistration' => $oldRegistration] = employeeWithEverything();
    $other = MedicalRegistration::factory()->approved()->create();
    Beneficiary::factory()->create(['medical_registration_id' => $other->id]);

    Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
        ->callAction('deleteEmployeeEntirely', ['confirm_employee_number' => '002876'])
        ->assertHasNoActionErrors()
        ->assertNotified('تم حذف الموظف '.$employee->full_name.' نهائياً')
        ->assertRedirect();

    expect(Employee::withTrashed()->find($employee->id))->toBeNull()
        ->and(MedicalRegistration::withTrashed()->whereKey([$registration->id, $oldRegistration->id])->count())->toBe(0)
        ->and(Beneficiary::queryWithCancelled()->withTrashed()->where('medical_registration_id', $registration->id)->count())->toBe(0)
        ->and(RegistrationReviewLog::query()->where('medical_registration_id', $registration->id)->count())->toBe(0)
        ->and(ReviewFlag::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('registrations/x'))->toBe([])
        ->and($other->fresh())->not->toBeNull()
        ->and($other->beneficiaries()->count())->toBe(1);
});

it('does nothing when the typed employee number does not match', function () {
    $this->actingAs(User::factory()->smartCare()->create());
    ['employee' => $employee] = employeeWithEverything();

    Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
        ->callAction('deleteEmployeeEntirely', ['confirm_employee_number' => '2876'])
        ->assertHasActionErrors(['confirm_employee_number']);

    expect($employee->fresh())->not->toBeNull();
});

it('hides the delete button from everyone except support', function () {
    $this->actingAs(User::factory()->hr()->create());
    $employee = Employee::factory()->create();

    Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
        ->assertActionHidden('deleteEmployeeEntirely');
});
