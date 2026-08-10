<?php

use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\RegistrationDocuments;
use Illuminate\Support\Facades\Storage;

it('blocks guests from registration documents', function () {
    Storage::fake('local');

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_photo_path' => 'registrations/demo/employee.jpg',
    ]);

    RegistrationDocuments::disk()->put($registration->employee_photo_path, 'fake-image');

    $this->get(route('registration.documents.show', [
        'registration' => $registration,
        'document' => RegistrationDocuments::EMPLOYEE_PHOTO,
    ]))->assertForbidden();
});

it('allows the registration owner session to view documents', function () {
    Storage::fake('local');

    $registration = MedicalRegistration::factory()->submitted()->create([
        'family_status_document_path' => 'registrations/demo/family.pdf',
    ]);

    RegistrationDocuments::disk()->put($registration->family_status_document_path, '%PDF-fake');

    $this->withSession(['registration_id' => $registration->id])
        ->get(route('registration.documents.show', [
            'registration' => $registration,
            'document' => RegistrationDocuments::FAMILY_STATUS,
        ]))
        ->assertSuccessful()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('allows authenticated admins to view documents and beneficiary photos', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_photo_path' => 'registrations/demo/employee.jpg',
    ]);
    $beneficiary = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'photo_path' => 'registrations/demo/beneficiary.jpg',
    ]);

    RegistrationDocuments::disk()->put($registration->employee_photo_path, 'fake-image');
    RegistrationDocuments::disk()->put($beneficiary->photo_path, 'fake-ben');

    $this->actingAs($admin)
        ->get(route('registration.documents.show', [
            'registration' => $registration,
            'document' => RegistrationDocuments::EMPLOYEE_PHOTO,
        ]))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get(route('registration.documents.beneficiary', [
            'registration' => $registration,
            'beneficiary' => $beneficiary,
        ]))
        ->assertSuccessful();
});

it('does not expose documents through the public storage path', function () {
    Storage::fake('local');
    Storage::fake('public');

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_photo_path' => 'registrations/secret/employee.jpg',
    ]);

    RegistrationDocuments::disk()->put($registration->employee_photo_path, 'secret-bytes');

    expect(Storage::disk('public')->exists($registration->employee_photo_path))->toBeFalse();

    $this->get('/storage/'.$registration->employee_photo_path)
        ->assertClientError();
});
