<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Filament\Pages\ScanInsuranceCards;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\InsuranceCardNumber;
use Livewire\Livewire;

it('hides the scan page from hr users', function () {
    $hr = User::factory()->hr()->create();

    $this->actingAs($hr);

    Livewire::test(ScanInsuranceCards::class)
        ->assertForbidden();

    $this->get(ScanInsuranceCards::getUrl())
        ->assertForbidden();
});

it('hides the scan page from reviewers', function () {
    $reviewer = User::factory()->reviewer()->create();

    $this->actingAs($reviewer);

    Livewire::test(ScanInsuranceCards::class)
        ->assertForbidden();

    $this->get(ScanInsuranceCards::getUrl())
        ->assertForbidden();
});

it('lets support users open the scan page', function () {
    $support = User::factory()->smartCare()->create();

    $this->actingAs($support);

    Livewire::test(ScanInsuranceCards::class)
        ->assertSuccessful()
        ->assertSee('مسح بطاقات التأمين')
        ->assertSee('لا توجد بطاقات ممسوحة بعد')
        ->assertActionVisible('markScannedPrinted')
        ->assertActionDisabled('markScannedPrinted');
});

it('rejects an invalid card number', function () {
    $support = User::factory()->smartCare()->create();

    $this->actingAs($support);

    Livewire::test(ScanInsuranceCards::class)
        ->set('scan', 'SC26-02278')
        ->call('scanCard')
        ->assertNotified()
        ->assertSet('scan', '')
        ->assertSet('scanned', []);
});

it('rejects a card number that does not exist', function () {
    $support = User::factory()->smartCare()->create();

    $this->actingAs($support);

    Livewire::test(ScanInsuranceCards::class)
        ->set('scan', '15893427')
        ->call('scanCard')
        ->assertNotified()
        ->assertSet('scanned', []);
});

it('groups scanned employee and family cards under one family', function () {
    $support = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'خالد صالح',
        'gender' => Gender::Male,
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'سارة خالد',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $employeeCard = $registration->employee->card_number;
    $spouseCard = $spouse->card_number;

    $this->actingAs($support);

    $page = Livewire::test(ScanInsuranceCards::class)
        ->set('scan', InsuranceCardNumber::display($employeeCard))
        ->call('scanCard')
        ->set('scan', $spouseCard)
        ->call('scanCard')
        ->assertSee('خالد صالح')
        ->assertSee('سارة خالد')
        ->assertSee('مكتملة')
        ->assertSee('hr-scan-groups', false);

    expect($page->instance()->scannedCount())->toBe(2)
        ->and($page->instance()->familyCount())->toBe(1)
        ->and($page->instance()->groups())->toHaveCount(1)
        ->and($page->instance()->groups()[0]['complete'])->toBeTrue()
        ->and(collect($page->instance()->groups()[0]['members'])->pluck('scanned')->all())->toBe([true, true]);
});

it('keeps different employees in separate family groups', function () {
    $support = User::factory()->smartCare()->create();
    $first = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'أحمد علي',
    ]);
    $second = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'منى العابد',
    ]);

    $this->actingAs($support);

    $page = Livewire::test(ScanInsuranceCards::class)
        ->set('scan', $first->employee->card_number)
        ->call('scanCard')
        ->set('scan', $second->employee->card_number)
        ->call('scanCard')
        ->assertSee('hr-scan-groups', false);

    expect($page->instance()->familyCount())->toBe(2)
        ->and($page->instance()->groups())->toHaveCount(2)
        ->and($page->instance()->groups()[0]['employee_name'])->toBe($second->employee->full_name);
});

it('does not add the same card twice and keeps the last scan on top', function () {
    $support = User::factory()->smartCare()->create();
    $first = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'أحمد علي',
    ]);
    $second = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'منى العابد',
    ]);

    $this->actingAs($support);

    $page = Livewire::test(ScanInsuranceCards::class)
        ->set('scan', $first->employee->card_number)
        ->call('scanCard')
        ->set('scan', $second->employee->card_number)
        ->call('scanCard')
        ->set('scan', $first->employee->card_number)
        ->call('scanCard');

    expect($page->instance()->scanned)->toHaveCount(2)
        ->and($page->instance()->groups()[0]['employee_name'])->toBe($first->employee->full_name)
        ->and($page->instance()->groups()[1]['employee_name'])->toBe($second->employee->full_name);
});

it('moves a family to the top when another of its cards is scanned', function () {
    $support = User::factory()->smartCare()->create();
    $first = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'خالد صالح',
        'gender' => Gender::Male,
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $first->id,
        'full_name' => 'سارة خالد',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $second = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'منى العابد',
    ]);

    $this->actingAs($support);

    $page = Livewire::test(ScanInsuranceCards::class)
        ->set('scan', $first->employee->card_number)
        ->call('scanCard')
        ->set('scan', $second->employee->card_number)
        ->call('scanCard')
        ->set('scan', $spouse->card_number)
        ->call('scanCard');

    expect($page->instance()->groups()[0]['employee_name'])->toBe($first->employee->full_name)
        ->and($page->instance()->groups()[0]['scanned_count'])->toBe(2);
});

it('marks only the scanned cards as printed', function () {
    $support = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'ليلى أحمد',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $child = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'يوسف أحمد',
        'relationship' => BeneficiaryRelationship::Son,
    ]);

    $this->actingAs($support);

    Livewire::test(ScanInsuranceCards::class)
        ->set('scan', $registration->employee->card_number)
        ->call('scanCard')
        ->set('scan', $spouse->card_number)
        ->call('scanCard')
        ->assertSee('ناقص 1')
        ->call('markScannedPrinted')
        ->assertNotified()
        ->assertSet('scanned', []);

    expect($registration->employee->fresh()->cardIsPrinted())->toBeTrue()
        ->and($spouse->fresh()->cardIsPrinted())->toBeTrue()
        ->and($child->fresh()->cardIsPrinted())->toBeFalse();
});

it('restores a scanned list saved in the browser and ignores invalid cache', function () {
    $support = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();

    $this->actingAs($support);

    $page = Livewire::test(ScanInsuranceCards::class)
        ->set('scan', $registration->employee->card_number)
        ->call('scanCard');

    $saved = $page->get('scanned');

    $page->call('clearScanned')
        ->assertSet('scanned', [])
        ->call('restoreScanned', $saved)
        ->assertSet('scanned', $saved)
        ->call('restoreScanned', [['name' => 'broken'], 'nope'])
        ->assertSet('scanned', []);
});

it('shows a client-side cache clear control on the scan page', function () {
    $support = User::factory()->smartCare()->create();

    $this->actingAs($support);

    Livewire::test(ScanInsuranceCards::class)
        ->assertSee('حذف الذاكرة')
        ->assertSeeHtml('estbyan.scan-insurance-cards');
});
