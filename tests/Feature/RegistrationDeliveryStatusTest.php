<?php

use App\Enums\CardDeliveryRecipient;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

it('shows on the registration page that the cards were not delivered yet', function () {
    $this->actingAs(User::factory()->hr()->create());
    $registration = MedicalRegistration::factory()->approved()->create();

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('تسليم البطاقات')
        ->assertSee('لم تُسلَّم بعد');
});

it('shows when, to whom and by whom the cards were delivered', function () {
    $deliveredBy = User::factory()->hr()->create(['name' => 'موظف التسليم']);
    $this->actingAs(User::factory()->hr()->create());
    $registration = MedicalRegistration::factory()->approved()->create();
    $registration->employee->markCardsDelivered($deliveredBy, CardDeliveryRecipient::Administration);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('تم التسليم إلى الإدارة')
        ->assertSee('بواسطة موظف التسليم')
        ->assertDontSee('لم تُسلَّم بعد');
});
