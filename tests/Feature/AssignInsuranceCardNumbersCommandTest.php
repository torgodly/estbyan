<?php

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\InsuranceCardNumber;

it('backfills missing card numbers for employees and their family members', function () {
    $registration = MedicalRegistration::factory()->create();
    $beneficiary = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
    ]);

    $registration->employee->forceFill(['card_number' => null])->saveQuietly();
    $beneficiary->forceFill(['card_number' => null])->saveQuietly();

    $this->artisan('insurance-cards:assign-numbers')
        ->assertSuccessful();

    $employee = $registration->employee->fresh();
    $beneficiary = $beneficiary->fresh();

    expect(InsuranceCardNumber::isCurrent($employee->card_number))->toBeTrue()
        ->and(InsuranceCardNumber::isCurrent($beneficiary->card_number))->toBeTrue()
        ->and($beneficiary->card_number)->not->toBe($employee->card_number);
});

it('upgrades zero-padded family-stem card numbers to unguessable current numbers', function () {
    $registration = MedicalRegistration::factory()->create();
    $beneficiary = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
    ]);

    $registration->employee->forceFill(['card_number' => '00158900'])->saveQuietly();
    $beneficiary->forceFill(['card_number' => '00158901'])->saveQuietly();

    $this->artisan('insurance-cards:assign-numbers')
        ->expectsOutputToContain('Assigned 1 employee card numbers and 1 family card numbers.')
        ->assertSuccessful();

    $employee = $registration->employee->fresh();
    $beneficiary = $beneficiary->fresh();

    expect($employee->card_number)->not->toBe('00158900')
        ->and($beneficiary->card_number)->not->toBe('00158901')
        ->and(InsuranceCardNumber::isCurrent($employee->card_number))->toBeTrue()
        ->and(InsuranceCardNumber::isCurrent($beneficiary->card_number))->toBeTrue()
        ->and($beneficiary->card_number)->not->toBe($employee->card_number)
        ->and(str_starts_with($beneficiary->card_number, '001589'))->toBeFalse();
});

it('does not change current card numbers that are already assigned', function () {
    $employee = Employee::factory()->create();
    $number = $employee->card_number;

    $this->artisan('insurance-cards:assign-numbers')
        ->expectsOutputToContain('Assigned 0 employee card numbers and 0 family card numbers.')
        ->assertSuccessful();

    expect($employee->fresh()->card_number)->toBe($number);
});
