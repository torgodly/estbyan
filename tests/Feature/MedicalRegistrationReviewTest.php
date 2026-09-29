<?php

use App\Enums\BloodType;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Services\RegistrationReviewService;
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
        ->and($registration->reviewed_at)->not->toBeNull()
        ->and($registration->reviewLogs()->count())->toBe(1)
        ->and($registration->reviewLogs()->first()->action)->toBe(RegistrationStatus::Approved)
        ->and($registration->reviewLogs()->first()->note)->toBe('مستوفي الشروط')
        ->and($registration->reviewLogs()->first()->user_id)->toBe($admin->id);
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
        ->and($registration->reviewed_by)->toBe($admin->id)
        ->and($registration->reviewLogs()->count())->toBe(1)
        ->and($registration->reviewLogs()->first()->action)->toBe(RegistrationStatus::Declined)
        ->and($registration->reviewLogs()->first()->note)->toBe('المستندات غير مكتملة');
});

it('shows review history when opening a registration', function () {
    $first = User::factory()->create(['name' => 'مراجع أول']);
    $second = User::factory()->create(['name' => 'مراجع ثان']);
    $registration = MedicalRegistration::factory()->submitted()->create();

    $service = app(RegistrationReviewService::class);
    $service->approve($registration, $first, 'اعتماد أولي');
    $service->decline($registration->fresh(), $second, 'نقص بيانات');

    $this->actingAs($second);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('سجل الاعتماد والرفض')
        ->assertSee('مراجع أول')
        ->assertSee('اعتماد أولي')
        ->assertSee('مراجع ثان')
        ->assertSee('نقص بيانات')
        ->assertSee('مقبول')
        ->assertSee('مرفوض');
});

it('renders the custom registration dossier with key sections', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'أحمد علي المراجعة',
        'blood_type' => BloodType::APositive,
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
        ->assertSee('فصيلة الدم')
        ->assertSee('A موجب')
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
        ->assertSee('سجل الاعتماد والرفض')
        ->assertSee('لا توجد قرارات اعتماد أو رفض بعد')
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
        ->assertSee('أمراض القلب و الشرايين')
        ->assertSee('الصرع');
});

it('shows the last submission time after an employee resubmits a declined registration', function () {
    $reviewer = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'submitted_at' => '2026-09-24 10:00:00',
    ]);

    app(RegistrationReviewService::class)->decline($registration, $reviewer, 'صور غير صحيحة');

    $registration->update([
        'status' => RegistrationStatus::Submitted,
        'submitted_at' => '2026-09-29 18:25:00',
        'review_note' => null,
        'reviewed_at' => null,
        'reviewed_by' => null,
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('الموظف عدّل وأعاد الإرسال')
        ->assertSee('آخر إرسال')
        ->assertSee('2026-09-29 18:25')
        ->assertSee('صور غير صحيحة')
        ->assertActionVisible('approve')
        ->assertActionVisible('decline');
});

it('does not treat a first submission as a resubmission after decline', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'submitted_at' => '2026-09-29 18:25:00',
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('آخر إرسال')
        ->assertSee('2026-09-29 18:25')
        ->assertDontSee('الموظف عدّل وأعاد الإرسال');
});

it('hides approve action for already approved registrations', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->approved()->create();

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertActionHidden('approve')
        ->assertActionVisible('decline');
});
