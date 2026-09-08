<?php

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\InsuranceCardNumber;

it('assigns an employee card number ending in 00 on create', function () {
    $employee = Employee::factory()->create();

    expect($employee->card_number)->toMatch('/^\d{6}00$/')
        ->and($employee->cardNumberLabel())->toBe('SC-'.$employee->card_number);
});

it('assigns family card numbers that share the employee stem', function () {
    $registration = MedicalRegistration::factory()->create();
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
    ]);
    $child = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
    ]);

    $stem = InsuranceCardNumber::stem($registration->employee->card_number);

    expect($registration->employee->card_number)->toBe($stem.'00')
        ->and($spouse->card_number)->toBe($stem.'01')
        ->and($child->card_number)->toBe($stem.'02')
        ->and(InsuranceCardNumber::employeeNumberFromFamily($spouse->card_number))
        ->toBe($registration->employee->card_number);
});

it('reuses the same family card number for the same person under the same employee', function () {
    $registration = MedicalRegistration::factory()->create();
    $first = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'national_id' => '219880112233',
    ]);

    $secondRegistration = MedicalRegistration::factory()->create([
        'employee_id' => $registration->employee_id,
    ]);

    $second = Beneficiary::factory()->create([
        'medical_registration_id' => $secondRegistration->id,
        'national_id' => '219880112233',
    ]);

    expect($second->card_number)->toBe($first->card_number);
});

it('gives a different family stem when the same person belongs to another employee', function () {
    $first = Beneficiary::factory()->create([
        'national_id' => '219880112233',
    ]);
    $second = Beneficiary::factory()->create([
        'national_id' => '219880112233',
    ]);

    expect($second->card_number)->not->toBe($first->card_number)
        ->and(InsuranceCardNumber::stem($second->card_number))
        ->not->toBe(InsuranceCardNumber::stem($first->card_number));
});
