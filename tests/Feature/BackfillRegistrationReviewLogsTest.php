<?php

use App\Enums\RegistrationStatus;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Models\MedicalRegistration;
use App\Models\RegistrationReviewLog;
use App\Models\User;
use App\Support\RegistrationReviewLogBackfill;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

it('backfills existing approve and decline decisions into the review history', function () {
    $reviewer = User::factory()->create(['name' => 'مصلحة الضرائب · الموارد البشرية']);

    $declined = MedicalRegistration::factory()->declined()->create([
        'full_name' => 'حليمة مصطفى محمد الاسطى',
        'reviewed_by' => $reviewer->id,
        'review_note' => 'لم يتم ارفاق الابن (عقبة) الرجاء الاضافة لاستكمال قبول المعاملة',
        'reviewed_at' => now()->subDay(),
    ]);
    $approved = MedicalRegistration::factory()->approved()->create([
        'reviewed_by' => $reviewer->id,
        'review_note' => 'مستوفي',
    ]);
    $alreadyLogged = MedicalRegistration::factory()->declined()->create([
        'reviewed_by' => $reviewer->id,
        'review_note' => 'مرفوض مسبقاً',
    ]);
    RegistrationReviewLog::factory()->create([
        'medical_registration_id' => $alreadyLogged->id,
        'user_id' => $reviewer->id,
        'action' => RegistrationStatus::Declined,
        'note' => 'مرفوض مسبقاً',
    ]);
    MedicalRegistration::factory()->submitted()->create();

    expect(RegistrationReviewLogBackfill::run())->toBe(2)
        ->and(RegistrationReviewLogBackfill::run())->toBe(0);

    expect($declined->reviewLogs()->count())->toBe(1)
        ->and($declined->reviewLogs()->first()->action)->toBe(RegistrationStatus::Declined)
        ->and($declined->reviewLogs()->first()->note)->toBe('لم يتم ارفاق الابن (عقبة) الرجاء الاضافة لاستكمال قبول المعاملة')
        ->and($declined->reviewLogs()->first()->user_id)->toBe($reviewer->id)
        ->and($approved->reviewLogs()->count())->toBe(1)
        ->and($alreadyLogged->reviewLogs()->count())->toBe(1);
});

it('shows a declined request reason even before the history table was filled', function () {
    $reviewer = User::factory()->create(['name' => 'مراجع الموارد']);
    $registration = MedicalRegistration::factory()->declined()->create([
        'reviewed_by' => $reviewer->id,
        'review_note' => 'لم يتم ارفاق الابن (عقبة) الرجاء الاضافة لاستكمال قبول المعاملة',
        'reviewed_at' => now()->subHours(2),
    ]);

    expect($registration->reviewLogs()->count())->toBe(0);

    $this->actingAs($reviewer);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('سجل الاعتماد والرفض')
        ->assertSee('مرفوض')
        ->assertSee('مراجع الموارد')
        ->assertSee('لم يتم ارفاق الابن (عقبة) الرجاء الاضافة لاستكمال قبول المعاملة')
        ->assertDontSee('لا توجد قرارات اعتماد أو رفض بعد');
});

it('runs the backfill command', function () {
    $reviewer = User::factory()->create();
    MedicalRegistration::factory()->declined()->create([
        'reviewed_by' => $reviewer->id,
    ]);

    Artisan::call('review-logs:backfill');

    expect(RegistrationReviewLog::query()->count())->toBe(1)
        ->and(Artisan::output())->toContain('Backfilled 1');
});
