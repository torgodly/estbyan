<?php

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\RegistrationDocuments;
use App\Support\TestEmployees;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('seeds the two fixed test employees', function () {
    Artisan::call('employees:seed-test');

    foreach (TestEmployees::definitions() as $definition) {
        $this->assertDatabaseHas(Employee::class, [
            'employee_number' => $definition['employee_number'],
            'national_id' => $definition['national_id'],
            'full_name' => $definition['full_name'],
            'is_active' => true,
        ]);
    }

    expect(Employee::query()->whereIn('employee_number', TestEmployees::employeeNumbers())->count())->toBe(2);
});

it('purges the test employees and related registration data', function () {
    Storage::fake(RegistrationDocuments::DISK);

    Artisan::call('employees:seed-test');

    $employee = Employee::query()
        ->where('employee_number', TestEmployees::employeeNumbers()[0])
        ->firstOrFail();

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'full_name' => $employee->full_name,
        'family_status_document_path' => 'registrations/test/family.pdf',
        'employee_photo_path' => 'registrations/test/photo.jpg',
    ]);

    RegistrationDocuments::disk()->put($registration->family_status_document_path, 'family');
    RegistrationDocuments::disk()->put($registration->employee_photo_path, 'photo');

    $beneficiary = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'photo_path' => 'registrations/test/beneficiary.jpg',
    ]);

    RegistrationDocuments::disk()->put($beneficiary->photo_path, 'beneficiary');

    Artisan::call('employees:purge-test', ['--force' => true]);

    expect(Employee::query()->whereIn('employee_number', TestEmployees::employeeNumbers())->count())->toBe(0)
        ->and(MedicalRegistration::query()->whereKey($registration->id)->exists())->toBeFalse()
        ->and(Beneficiary::query()->whereKey($beneficiary->id)->exists())->toBeFalse()
        ->and(RegistrationDocuments::disk()->exists('registrations/test/family.pdf'))->toBeFalse()
        ->and(RegistrationDocuments::disk()->exists('registrations/test/photo.jpg'))->toBeFalse()
        ->and(RegistrationDocuments::disk()->exists('registrations/test/beneficiary.jpg'))->toBeFalse();
});
