<?php

use App\Enums\BeneficiaryCancellationReason;
use App\Enums\BeneficiaryRelationship;
use App\Enums\CardDeliveryRecipient;
use App\Enums\Gender;
use App\Filament\Pages\DeliverInsuranceCards;
use App\Filament\Pages\ScanInsuranceCards;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Services\InsuranceCardNumberAssigner;
use Livewire\Livewire;

/**
 * @return array{husband: MedicalRegistration, wife: MedicalRegistration, wifeAsFamily: Beneficiary}
 */
function familyWithCancelledSpouseCard(): array
{
    $husband = MedicalRegistration::factory()->submitted()->create(['gender' => Gender::Male]);
    $wife = MedicalRegistration::factory()->submitted()->create(['gender' => Gender::Female]);

    $wifeAsFamily = Beneficiary::factory()->create([
        'medical_registration_id' => $husband->id,
        'full_name' => $wife->employee->full_name,
        'relationship' => BeneficiaryRelationship::Spouse,
        'national_id' => $wife->employee->national_id,
    ]);
    $wifeAsFamily->markCardPrinted();
    $husband->employee->markCardPrinted();
    $wife->employee->markCardPrinted();

    $wifeAsFamily->refresh()->cancel(BeneficiaryCancellationReason::EmployeeDuplicate);

    return ['husband' => $husband, 'wife' => $wife, 'wifeAsFamily' => $wifeAsFamily->fresh()];
}

it('hides cancelled family members from normal queries and the registration', function () {
    ['husband' => $husband, 'wifeAsFamily' => $wifeAsFamily] = familyWithCancelledSpouseCard();

    expect(Beneficiary::query()->find($wifeAsFamily->id))->toBeNull()
        ->and($husband->fresh()->beneficiaries()->count())->toBe(0)
        ->and(Beneficiary::queryWithCancelled()->find($wifeAsFamily->id))->not->toBeNull();
});

it('warns and refuses to add a cancelled card on the delivery page', function () {
    ['husband' => $husband, 'wifeAsFamily' => $wifeAsFamily] = familyWithCancelledSpouseCard();

    $this->actingAs(User::factory()->cardDelivery()->create());

    Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $wifeAsFamily->card_number)
        ->call('scanCard')
        ->assertNotified('بطاقة ملغاة: '.$wifeAsFamily->full_name)
        ->assertSet('scanned', [])
        ->set('scan', $husband->employee->card_number)
        ->call('scanCard')
        ->assertSee('جاهزة للتسليم')
        ->assertActionEnabled('markDelivered')
        ->callAction('markDelivered', [
            'delivered_to' => CardDeliveryRecipient::Employee->value,
        ]);

    expect($husband->employee->fresh()->cardsAreDelivered())->toBeTrue();
});

it('still delivers the employee card of someone whose family card was cancelled', function () {
    ['wife' => $wife] = familyWithCancelledSpouseCard();

    $this->actingAs(User::factory()->cardDelivery()->create());

    Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $wife->employee->card_number)
        ->call('scanCard')
        ->assertSee('جاهزة للتسليم')
        ->assertActionEnabled('markDelivered');
});

it('warns and refuses to add a cancelled card on the print scan page', function () {
    ['wifeAsFamily' => $wifeAsFamily] = familyWithCancelledSpouseCard();

    $this->actingAs(User::factory()->smartCare()->create());

    Livewire::test(ScanInsuranceCards::class)
        ->set('scan', $wifeAsFamily->card_number)
        ->call('scanCard')
        ->assertNotified('بطاقة ملغاة: '.$wifeAsFamily->full_name)
        ->assertSet('scanned', []);
});

it('never reuses the card number of a cancelled family member', function () {
    ['husband' => $husband, 'wifeAsFamily' => $wifeAsFamily] = familyWithCancelledSpouseCard();

    $newMember = new Beneficiary([
        'medical_registration_id' => $husband->id,
        'full_name' => 'عضو جديد',
        'relationship' => BeneficiaryRelationship::Son,
        'card_number' => $wifeAsFamily->card_number,
    ]);

    app(InsuranceCardNumberAssigner::class)->fillBeneficiary($newMember, replaceLegacy: true);

    expect($newMember->card_number)->not->toBe($wifeAsFamily->card_number);
});
