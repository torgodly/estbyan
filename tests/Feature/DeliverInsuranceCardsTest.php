<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\CardDeliveryRecipient;
use App\Enums\Gender;
use App\Filament\Pages\DeliverInsuranceCards;
use App\Filament\Pages\ExportPrintedCards;
use App\Filament\Pages\ScanInsuranceCards;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

function markFamilyCardsPrinted(MedicalRegistration $registration): void
{
    $registration->employee?->markCardPrinted();

    $registration->loadMissing('beneficiaries');

    foreach ($registration->beneficiaries as $beneficiary) {
        $beneficiary->markCardPrinted();
    }
}

it('lets region delivery accounts open only the delivery page', function () {
    $east = User::factory()->cardDelivery()->create([
        'name' => 'المنطقة الشرقية',
    ]);

    $this->actingAs($east);

    Livewire::test(DeliverInsuranceCards::class)
        ->assertSuccessful()
        ->assertSee('تسليم بطاقات التأمين')
        ->assertSee('بما فيهم الأب والأم');

    Livewire::test(ListEmployees::class)->assertForbidden();
    Livewire::test(ScanInsuranceCards::class)->assertForbidden();
    Livewire::test(ExportPrintedCards::class)->assertForbidden();
});

it('hides the delivery page from reviewers', function () {
    $reviewer = User::factory()->reviewer()->create();

    $this->actingAs($reviewer);

    Livewire::test(DeliverInsuranceCards::class)->assertForbidden();
});

it('lets smart care users open the delivery page', function () {
    $support = User::factory()->smartCare()->create();

    $this->actingAs($support);

    Livewire::test(DeliverInsuranceCards::class)
        ->assertSuccessful()
        ->assertActionVisible('markDelivered')
        ->assertActionDisabled('markDelivered');
});

it('requires every family member including parents before delivery', function () {
    $east = User::factory()->cardDelivery()->create([
        'name' => 'المنطقة الشرقية',
    ]);
    $registration = MedicalRegistration::factory()->submitted()->create([
        'gender' => Gender::Male,
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'فاطمة الزوجة',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $father = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'أحمد الأب',
        'relationship' => BeneficiaryRelationship::Father,
    ]);
    $mother = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'منى الأم',
        'relationship' => BeneficiaryRelationship::Mother,
    ]);
    markFamilyCardsPrinted($registration);

    $this->actingAs($east);

    $page = Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $registration->employee->card_number)
        ->call('scanCard')
        ->set('scan', $spouse->fresh()->card_number)
        ->call('scanCard')
        ->assertSee('ناقص')
        ->assertSee('أحمد الأب')
        ->assertSee('منى الأم')
        ->assertActionDisabled('markDelivered')
        ->set('scan', $father->fresh()->card_number)
        ->call('scanCard')
        ->set('scan', $mother->fresh()->card_number)
        ->call('scanCard')
        ->assertSee('جاهزة للتسليم')
        ->assertActionEnabled('markDelivered')
        ->callAction('markDelivered', [
            'delivered_to' => CardDeliveryRecipient::Employee->value,
        ])
        ->assertNotified('تم تسليم بطاقات الموظف '.$registration->employee->full_name.' بنجاح')
        ->assertRedirect(DeliverInsuranceCards::getUrl());

    $employee = $registration->employee->fresh();

    expect($employee->cardsAreDelivered())->toBeTrue()
        ->and($employee->cards_delivered_to)->toBe(CardDeliveryRecipient::Employee)
        ->and($employee->cards_delivered_by)->toBe($east->id)
        ->and($employee->cards_delivered_at)->not->toBeNull();

    $this->actingAs($east);

    Livewire::test(DeliverInsuranceCards::class)
        ->assertSee('تم تسليم بطاقات الموظف')
        ->assertSee($employee->full_name)
        ->assertSee('لا توجد عائلة قيد التسليم');
});

it('records who delivered and when, and treats delivered families as view-only', function () {
    $west = User::factory()->cardDelivery()->create([
        'name' => 'المنطقة الغربية',
    ]);
    $registration = MedicalRegistration::factory()->submitted()->create([
        'gender' => Gender::Male,
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    markFamilyCardsPrinted($registration);
    $registration->employee->markCardsDelivered($west, CardDeliveryRecipient::Administration);

    $this->actingAs($west);

    Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $registration->employee->card_number)
        ->call('scanCard')
        ->set('scan', $spouse->fresh()->card_number)
        ->call('scanCard')
        ->assertSee('مُسلّمة')
        ->assertSee('تم التسليم سابقاً — للعرض فقط')
        ->assertSee('إلى الإدارة')
        ->assertSee('بواسطة المنطقة الغربية')
        ->assertSee($registration->employee->fresh()->cards_delivered_at->timezone('Africa/Tripoli')->format('Y-m-d H:i'))
        ->assertActionDisabled('markDelivered')
        ->call('markDelivered', CardDeliveryRecipient::Employee->value)
        ->assertNotified('تم التسليم مسبقاً — للعرض فقط');

    expect($registration->employee->fresh()->cards_delivered_to)->toBe(CardDeliveryRecipient::Administration);
});

it('blocks scanning another family until the current one is delivered or cleared', function () {
    $east = User::factory()->cardDelivery()->create();
    $first = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'خالد صالح',
        'gender' => Gender::Male,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $first->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $second = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'منى العابد',
    ]);
    markFamilyCardsPrinted($first);
    markFamilyCardsPrinted($second);

    $this->actingAs($east);

    $page = Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $first->employee->card_number)
        ->call('scanCard')
        ->set('scan', $second->employee->card_number)
        ->call('scanCard')
        ->assertNotified();

    expect($page->instance()->scanned)->toHaveCount(1)
        ->and($page->instance()->family()['employee_name'])->toBe($first->employee->full_name);
});
