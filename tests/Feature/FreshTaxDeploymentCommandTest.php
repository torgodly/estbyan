<?php

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\RegistrationDocuments;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('wipes previous data and imports the tax authority roster', function () {
    Storage::fake(RegistrationDocuments::DISK);

    $oldEmployee = Employee::factory()->create([
        'employee_number' => '999999',
        'full_name' => 'موظف قديم',
    ]);

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $oldEmployee->id,
        'employee_number' => $oldEmployee->employee_number,
        'employee_photo_path' => 'registrations/old/photo.jpg',
    ]);

    RegistrationDocuments::disk()->put($registration->employee_photo_path, 'photo');

    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'photo_path' => 'registrations/old/beneficiary.jpg',
    ]);

    RegistrationDocuments::disk()->put('registrations/old/beneficiary.jpg', 'beneficiary');

    Artisan::call('deployment:fresh-tax', ['--force' => true]);

    expect(Employee::query()->whereKey($oldEmployee->id)->exists())->toBeFalse()
        ->and(MedicalRegistration::query()->whereKey($registration->id)->exists())->toBeFalse()
        ->and(Beneficiary::query()->count())->toBe(0)
        ->and(Employee::query()->where('is_active', true)->count())->toBeGreaterThan(4000)
        ->and(Employee::query()->where('employee_number', '007017')->exists())->toBeTrue()
        ->and(RegistrationDocuments::disk()->exists('registrations/old/photo.jpg'))->toBeFalse();
});
