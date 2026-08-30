<?php

use App\Enums\Gender;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Settings\RegistrationSettings;
use App\Support\LibyanNationalId;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = true;
    $settings->save();

    RateLimiter::clear('registration-verify:ip:127.0.0.1');
});

it('persists changes only to the registration bound to the session', function () {
    $employeeA = LibyanNationalId::generate(Gender::Male, 1980);
    $employeeB = LibyanNationalId::generate(Gender::Male, 1981);

    Employee::factory()->create([
        'employee_number' => '9601',
        'national_id' => $employeeA,
        'full_name' => 'موظف أ',
        'workplace' => 'tripoli',
    ]);

    $employeeBModel = Employee::factory()->create([
        'employee_number' => '9602',
        'national_id' => $employeeB,
        'full_name' => 'موظف ب',
        'workplace' => 'benghazi',
    ]);

    $otherRegistration = MedicalRegistration::factory()->create([
        'employee_id' => $employeeBModel->id,
        'employee_number' => $employeeBModel->employee_number,
        'national_id' => $employeeBModel->national_id,
        'full_name' => $employeeBModel->full_name,
        'workplace' => 'benghazi',
        'phone' => null,
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeA)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertHasNoErrors()
        ->set('dateOfBirth', '1980-01-15')
        ->set('city', 'tripoli')
        ->set('address', 'طرابلس')
        ->set('phone', '0911223344')
        ->call('saveEmployeeDetails')
        ->assertHasNoErrors();

    $sessionRegistrationId = session('registration_id');

    expect($sessionRegistrationId)->not->toBe($otherRegistration->id);

    $otherRegistration->refresh();
    $sessionRegistration = MedicalRegistration::query()->find($sessionRegistrationId);

    expect($otherRegistration->phone)->toBeNull()
        ->and($sessionRegistration?->phone)->toBe('0911223344');
});

it('rate limits identity verification attempts by ip', function () {
    Employee::factory()->create([
        'employee_number' => '9603',
        'national_id' => '119800123456',
        'full_name' => 'موظف للحد',
        'workplace' => 'tripoli',
    ]);

    $component = Livewire::test(MedicalRegistrationForm::class);

    foreach (range(1, 10) as $attempt) {
        $component
            ->set('nationalId', '119800123456')
            ->set('consent', true)
            ->call('verifyIdentity');
    }

    $component
        ->set('nationalId', '119800123456')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertHasErrors(['nationalId']);
});

it('rate limits repeated attempts against the same national id', function () {
    RateLimiter::clear('registration-verify:nid:119800123456');

    $component = Livewire::test(MedicalRegistrationForm::class);

    foreach (range(1, 5) as $attempt) {
        $component
            ->set('nationalId', '119800123456')
            ->set('consent', true)
            ->call('verifyIdentity');
    }

    $component
        ->set('nationalId', '119800123456')
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertHasErrors(['nationalId']);
});
