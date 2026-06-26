<?php

use App\Livewire\MedicalRegistrationForm;
use App\Models\MedicalRegistration;
use App\Settings\RegistrationSettings;
use Livewire\Livewire;

it('redirects home to registration form', function () {
    $this->get('/')->assertRedirect('/register');
});

it('shows registration form when enabled', function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = true;
    $settings->save();

    $this->get('/register')->assertSuccessful();
});

it('shows closed page when form is disabled', function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = false;
    $settings->save();

    $this->get('/register')->assertStatus(503);
});

it('accepts identity details and advances to step 2 without employee lookup', function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = true;
    $settings->save();

    Livewire::test(MedicalRegistrationForm::class)
        ->set('fullName', 'أحمد محمد')
        ->set('employeeNumber', '2001')
        ->set('nationalId', '1234567890123')
        ->set('dateOfBirth', '1985-03-10')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertSet('step', 2)
        ->assertSet('verifiedFullName', 'أحمد محمد');

    expect(MedicalRegistration::query()->where('employee_number', '2001')->exists())->toBeTrue();
});

it('requires full name on step one', function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = true;
    $settings->save();

    Livewire::test(MedicalRegistrationForm::class)
        ->set('fullName', '')
        ->set('employeeNumber', '9999')
        ->set('nationalId', '0000000000000')
        ->set('dateOfBirth', '1990-01-01')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertHasErrors('fullName')
        ->assertSet('step', 1);
});

it('persists step one draft in session and restores on remount', function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = true;
    $settings->save();

    Livewire::test(MedicalRegistrationForm::class)
        ->set('fullName', 'محمد كريم')
        ->set('employeeNumber', '1001')
        ->set('nationalId', '1039485762104')
        ->set('dateOfBirth', '1970-05-16')
        ->set('consent', true)
        ->assertSet('hasSavedDraft', true);

    Livewire::test(MedicalRegistrationForm::class)
        ->assertSet('fullName', 'محمد كريم')
        ->assertSet('employeeNumber', '1001')
        ->assertSet('nationalId', '1039485762104')
        ->assertSet('dateOfBirth', '1970-05-16')
        ->assertSet('consent', true)
        ->assertSet('hasSavedDraft', true);
});

it('restores registration after refresh simulation', function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = true;
    $settings->save();

    Livewire::test(MedicalRegistrationForm::class)
        ->set('fullName', 'سارة علي')
        ->set('employeeNumber', '3001')
        ->set('nationalId', '9876543210987')
        ->set('dateOfBirth', '1980-01-01')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('workplace', 'southwest_tripoli')
        ->set('city', 'tripoli')
        ->set('beneficiariesCount', '2')
        ->set('address', 'طرابلس')
        ->set('phone', '0912345678')
        ->call('saveEmployeeDetails');

    $registration = MedicalRegistration::query()->where('employee_number', '3001')->first();

    expect($registration)->not->toBeNull()
        ->and($registration->phone)->toBe('0912345678')
        ->and($registration->current_step)->toBe(3)
        ->and($registration->employee_id)->toBeNull();

    Livewire::test(MedicalRegistrationForm::class)
        ->assertSet('step', 3)
        ->assertSet('phone', '0912345678')
        ->assertSet('verifiedFullName', 'سارة علي');
});

it('clears all form data and session', function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = true;
    $settings->save();

    Livewire::test(MedicalRegistrationForm::class)
        ->set('fullName', 'خالد أحمد')
        ->set('employeeNumber', '4001')
        ->set('nationalId', '1111111111111')
        ->set('dateOfBirth', '1975-06-15')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->call('clearForm')
        ->assertSet('step', 1)
        ->assertSet('employeeNumber', '')
        ->assertSet('registrationId', null);

    expect(session('registration_id'))->toBeNull()
        ->and(MedicalRegistration::query()->where('employee_number', '4001')->exists())->toBeFalse();
});
