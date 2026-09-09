<?php

use App\Enums\UserRole;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

it('hides insurance cards and print actions from hr users', function () {
    $hr = User::factory()->hr()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف الموارد البشرية',
        'reference_number' => 'SC26-08888',
    ]);

    $this->actingAs($hr);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('موظف الموارد البشرية')
        ->assertDontSee('بطاقات التأمين')
        ->assertDontSee('employee-insurance-cards--preview')
        ->assertDontSee('insurance-cards-print')
        ->assertDontSee('طباعة الكل')
        ->assertDontSee('طباعة هذه البطاقة')
        ->assertActionHidden('downloadInsuranceCards')
        ->assertActionHidden('printInsuranceCards');
});

it('forbids hr users from toggling a printed insurance card', function () {
    $hr = User::factory()->hr()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();

    $this->actingAs($hr);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->call('toggleInsuranceCardPrinted', 'employee')
        ->assertForbidden();

    expect($registration->employee->fresh()->cardIsPrinted())->toBeFalse();
});

it('lets smart care users see and print insurance cards', function () {
    $support = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف سمارت كير',
        'reference_number' => 'SC26-09999',
    ]);

    $this->actingAs($support);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('بطاقات التأمين')
        ->assertSee('employee-insurance-cards--preview', false)
        ->assertActionVisible('downloadInsuranceCards')
        ->assertActionVisible('printInsuranceCards');

    expect($support->role)->toBe(UserRole::SmartCare)
        ->and($support->canManageInsuranceCards())->toBeTrue();
});
