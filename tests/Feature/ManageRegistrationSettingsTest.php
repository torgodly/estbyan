<?php

use App\Filament\Pages\ManageRegistrationSettings;
use App\Models\User;
use App\Settings\RegistrationSettings;
use Livewire\Livewire;

it('can re-enable the registration form without crashing', function () {
    $admin = User::factory()->create();
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = false;
    $settings->disabled_message_ar = 'مغلق مؤقتاً';
    $settings->disabled_message_en = 'Temporarily closed';
    $settings->save();

    Livewire::actingAs($admin)
        ->test(ManageRegistrationSettings::class)
        ->assertSee('رسائل صفحة الإغلاق')
        ->fillForm([
            'form_enabled' => true,
        ])
        ->assertDontSee('رسائل صفحة الإغلاق')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $settings = app(RegistrationSettings::class);

    expect($settings->form_enabled)->toBeTrue()
        ->and($settings->disabled_message_ar)->toBe('مغلق مؤقتاً')
        ->and($settings->disabled_message_en)->toBe('Temporarily closed');
});

it('keeps disabled messages when saving with only the toggle present', function () {
    $admin = User::factory()->create();
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = false;
    $settings->disabled_message_ar = 'الرسالة الأصلية';
    $settings->disabled_message_en = 'Original message';
    $settings->save();

    $page = Livewire::actingAs($admin)->test(ManageRegistrationSettings::class);

    $page->instance()->save();

    $settings = app(RegistrationSettings::class);

    expect($settings->disabled_message_ar)->toBe('الرسالة الأصلية')
        ->and($settings->disabled_message_en)->toBe('Original message');
});
