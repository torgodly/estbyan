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

    expect($employee->card_number)->toMatch('/^\d{6}00$/')
        ->and($beneficiary->card_number)->toBe(InsuranceCardNumber::stem($employee->card_number).'01');
});

it('does not change card numbers that are already assigned', function () {
    $employee = Employee::factory()->create();
    $number = $employee->card_number;

    $this->artisan('insurance-cards:assign-numbers')
        ->expectsOutputToContain('Assigned 0 employee card numbers and 0 family card numbers.')
        ->assertSuccessful();

    expect($employee->fresh()->card_number)->toBe($number);
});
