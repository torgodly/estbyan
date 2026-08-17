<?php

use App\Enums\Gender;
use App\Enums\RegistrationStatus;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\LibyanNationalId;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('assigns the next reference from the highest sequence not the newest id', function () {
    MedicalRegistration::factory()->submitted()->create([
        'reference_number' => 'SC26-00010',
    ]);

    // Newer row with a lower reference — previously broke allocation via orderByDesc('id').
    MedicalRegistration::factory()->submitted()->create([
        'reference_number' => 'SC26-00003',
    ]);

    $next = DB::transaction(fn (): string => MedicalRegistration::generateReferenceNumber());

    expect($next)->toBe('SC26-00011');
});

it('submits without colliding when a lower reference exists on a newer row', function () {
    MedicalRegistration::factory()->submitted()->create([
        'reference_number' => 'SC26-00010',
    ]);
    MedicalRegistration::factory()->submitted()->create([
        'reference_number' => 'SC26-00003',
    ]);

    $nationalId = LibyanNationalId::generate(Gender::Male, 1990);
    $employee = Employee::factory()->create([
        'employee_number' => '88001',
        'national_id' => $nationalId,
        'full_name' => 'موظف مرجع',
        'workplace' => 'general_admin',
    ]);

    $registration = MedicalRegistration::factory()->create([
        'employee_id' => $employee->id,
        'employee_number' => '88001',
        'national_id' => $nationalId,
        'full_name' => 'موظف مرجع',
        'workplace' => 'general_admin',
        'status' => RegistrationStatus::Draft,
        'reference_number' => null,
        'family_status_document_path' => 'registrations/demo/family.pdf',
        'employee_photo_path' => 'registrations/demo/employee.jpg',
        'current_step' => 6,
        'consent_at' => now(),
        'date_of_birth' => '1990-01-01',
        'phone' => '0910000000',
        'city' => 'tripoli',
        'address' => 'طرابلس',
        'beneficiaries_count' => 0,
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '88001')
        ->set('nationalId', $nationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 6)
        ->set('hasFamilyDocument', true)
        ->set('hasEmployeePhoto', true)
        ->call('submitRegistration')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertSet('referenceNumber', 'SC26-00011');

    expect($registration->fresh()->reference_number)->toBe('SC26-00011')
        ->and($registration->fresh()->status)->toBe(RegistrationStatus::Submitted);
});
