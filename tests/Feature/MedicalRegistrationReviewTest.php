<?php

use App\Enums\RegistrationStatus;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

it('approves a registration with an optional note', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->callAction('approve', data: [
            'review_note' => 'مستوفي الشروط',
        ])
        ->assertHasNoActionErrors();

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Approved)
        ->and($registration->review_note)->toBe('مستوفي الشروط')
        ->and($registration->reviewed_by)->toBe($admin->id)
        ->and($registration->reviewed_at)->not->toBeNull();
});

it('declines a registration and requires a note', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->callAction('decline', data: [
            'review_note' => '',
        ])
        ->assertHasActionErrors(['review_note']);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->callAction('decline', data: [
            'review_note' => 'المستندات غير مكتملة',
        ])
        ->assertHasNoActionErrors();

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Declined)
        ->and($registration->review_note)->toBe('المستندات غير مكتملة')
        ->and($registration->reviewed_by)->toBe($admin->id);
});

it('renders the custom registration dossier with key sections', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'أحمد علي المراجعة',
        'phone' => '0910000000',
        'has_chronic_conditions' => true,
        'chronic_conditions' => ['diabetes'],
        'family_status_document_path' => null,
        'employee_photo_path' => null,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'سارة علي',
        'photo_path' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('أحمد علي المراجعة')
        ->assertSee($registration->reference_number)
        ->assertSee('السجل الطبي للموظف')
        ->assertSee('هل يعاني من أمراض مزمنة؟')
        ->assertSee('اضغط لعرض التفاصيل المحددة')
        ->assertSee('تنبيه طبي')
        ->assertSee('المستندات')
        ->assertSee('صورة من شهادة الوضع العائلي')
        ->assertSee('صورة الموظف')
        ->assertSee('لم يُرفق هذا المستند')
        ->assertSee('لم تُرفع صورة الموظف')
        ->assertSee('معاينة مباشرة داخل الصفحة')
        ->assertSee('المستفيدون')
        ->assertSee('سارة علي')
        ->assertSee('بدون صورة')
        ->assertSee('ملخص المراجعة')
        ->assertSee('سجل القرار')
        ->assertActionVisible('approve')
        ->assertActionVisible('decline');
});

it('shows selected chronic conditions inside the medical accordion details', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'has_chronic_conditions' => true,
        'chronic_conditions' => ['heart_disease', 'epilepsy'],
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('الأمراض المزمنة المحددة')
        ->assertSee('أمراض القلب')
        ->assertSee('الصرع');
});

it('hides approve action for already approved registrations', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->approved()->create();

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertActionHidden('approve')
        ->assertActionVisible('decline');
});
