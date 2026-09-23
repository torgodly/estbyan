<?php

use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\RegistrationDocuments;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

    $response = $this->withSession(['registration_id' => $registration->id])
        ->get(route('registration.documents.show', [
            'registration' => $registration,
            'document' => RegistrationDocuments::FAMILY_STATUS,
        ]));

    $response->assertSuccessful()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('changes the employee photo url when the stored file is replaced', function () {
    Storage::fake('local');

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_photo_path' => 'registrations/demo/employee.jpg',
    ]);

    RegistrationDocuments::disk()->put($registration->employee_photo_path, 'old-bytes');

    $before = RegistrationDocuments::url($registration, RegistrationDocuments::EMPLOYEE_PHOTO);

    $registration->update([
        'employee_photo_path' => 'registrations/demo/employee-new.jpg',
    ]);
    RegistrationDocuments::disk()->put($registration->employee_photo_path, 'new-bytes');

    $after = RegistrationDocuments::url($registration->fresh(), RegistrationDocuments::EMPLOYEE_PHOTO);

    expect($before)->not->toBe($after)
        ->and($after)->toContain('v=');
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

it('classifies heic family documents as a downloadable file, not a pdf preview', function () {
    expect(RegistrationDocuments::browserPreviewKind('registrations/demo/family.heic'))->toBe('file')
        ->and(RegistrationDocuments::browserPreviewKind('registrations/demo/family.heif'))->toBe('file')
        ->and(RegistrationDocuments::browserPreviewKind('registrations/demo/family.pdf'))->toBe('pdf')
        ->and(RegistrationDocuments::browserPreviewKind('registrations/demo/family.jpg'))->toBe('image');
});

it('does not embed a heic family document in an iframe on the registration file', function () {
    Storage::fake('local');

    $admin = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'family_status_document_path' => 'registrations/demo/family.heic',
    ]);
    RegistrationDocuments::disk()->put($registration->family_status_document_path, 'heic-bytes');

    $familyUrl = RegistrationDocuments::url($registration, RegistrationDocuments::FAMILY_STATUS);

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertDontSeeHtml('<iframe src="'.$familyUrl)
        ->assertSee('لا يمكن عرض ملف HEIC داخل الصفحة')
        ->assertSee('تحميل الشهادة');
});
