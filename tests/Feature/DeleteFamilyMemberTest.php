<?php

use App\Enums\BeneficiaryCancellationReason;
use App\Enums\BeneficiaryRelationship;
use App\Enums\ReviewFlagReason;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;
use App\Models\User;
use App\Services\FamilyMemberDeleter;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('lets support delete a family member with their photo and review flags', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->smartCare()->create());

    $registration = MedicalRegistration::factory()->approved()->create(['beneficiaries_count' => 2]);
    $son = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Son,
        'photo_path' => 'registrations/x/son.jpg',
    ]);
    $daughter = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Daughter,
        'photo_path' => 'registrations/x/daughter.jpg',
    ]);
    ReviewFlag::raise($son, ReviewFlagReason::PossibleDuplicateFamilyMember, 'x');
    Storage::disk('local')->put('registrations/x/son.jpg', 'content');
    Storage::disk('local')->put('registrations/x/daughter.jpg', 'content');

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSee('حذف نهائي')
        ->assertActionVisible(TestAction::make('deleteFamilyMember')->arguments(['beneficiary' => $son->id]))
        ->callAction(TestAction::make('deleteFamilyMember')->arguments(['beneficiary' => $son->id]))
        ->assertHasNoActionErrors()
        ->assertNotified('تم حذف '.$son->full_name.' نهائياً');

    expect(Beneficiary::queryWithCancelled()->withTrashed()->find($son->id))->toBeNull()
        ->and(ReviewFlag::withTrashed()->count())->toBe(0)
        ->and(Storage::disk('local')->exists('registrations/x/son.jpg'))->toBeFalse()
        ->and($daughter->fresh())->not->toBeNull()
        ->and(Storage::disk('local')->exists('registrations/x/daughter.jpg'))->toBeTrue()
        ->and($registration->fresh()->beneficiaries_count)->toBe(1);
});

it('also deletes cancelled and soft-deleted family members', function () {
    $registration = MedicalRegistration::factory()->create();
    $cancelled = Beneficiary::factory()->create(['medical_registration_id' => $registration->id]);
    $cancelled->cancel(BeneficiaryCancellationReason::EmployeeDuplicate);
    $trashed = Beneficiary::factory()->create(['medical_registration_id' => $registration->id]);
    $trashed->delete();

    app(FamilyMemberDeleter::class)->delete($cancelled->id);
    app(FamilyMemberDeleter::class)->delete($trashed->id);

    expect(Beneficiary::queryWithCancelled()->withTrashed()->whereKey([$cancelled->id, $trashed->id])->count())->toBe(0);
});

it('keeps a photo that another record still uses', function () {
    Storage::fake('local');
    $registration = MedicalRegistration::factory()->create();
    $first = Beneficiary::factory()->create(['medical_registration_id' => $registration->id, 'photo_path' => 'registrations/x/shared.jpg']);
    Beneficiary::factory()->create(['medical_registration_id' => $registration->id, 'photo_path' => 'registrations/x/shared.jpg']);
    Storage::disk('local')->put('registrations/x/shared.jpg', 'content');

    $result = app(FamilyMemberDeleter::class)->delete($first->id);

    expect($result['photo_deleted'])->toBeFalse()
        ->and(Storage::disk('local')->exists('registrations/x/shared.jpg'))->toBeTrue();
});

it('cannot delete a family member of another registration from this page', function () {
    $this->actingAs(User::factory()->smartCare()->create());
    $registration = MedicalRegistration::factory()->approved()->create();
    Beneficiary::factory()->create(['medical_registration_id' => $registration->id]);
    $otherMember = Beneficiary::factory()->create(['medical_registration_id' => MedicalRegistration::factory()->create()->id]);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->callAction(TestAction::make('deleteFamilyMember')->arguments(['beneficiary' => $otherMember->id]));

    expect($otherMember->fresh())->not->toBeNull();
});

it('hides the delete button from everyone except support', function () {
    $this->actingAs(User::factory()->hr()->create());
    $registration = MedicalRegistration::factory()->approved()->create();
    $member = Beneficiary::factory()->create(['medical_registration_id' => $registration->id]);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertActionHidden(TestAction::make('deleteFamilyMember')->arguments(['beneficiary' => $member->id]))
        ->assertDontSee('حذف نهائي');

    expect($member->fresh())->not->toBeNull();
});
