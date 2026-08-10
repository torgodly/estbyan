<?php

use App\Enums\RegistrationStatus;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
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
