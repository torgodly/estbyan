<?php

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\InsuranceCardNumber;

it('assigns a random eight-digit employee card number with no leading zero', function () {
    $employee = Employee::factory()->create();

    expect(InsuranceCardNumber::isCurrent($employee->card_number))->toBeTrue()
        ->and($employee->cardNumberLabel())->toBe('SC-'.$employee->card_number);
});

it('assigns unguessable family card numbers that are not derived from the employee card', function () {
    $registration = MedicalRegistration::factory()->create();
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
    ]);
    $child = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
    ]);

    $employeeNumber = $registration->employee->card_number;
    $employeePrefix = substr($employeeNumber, 0, 6);

    expect(InsuranceCardNumber::isCurrent($spouse->card_number))->toBeTrue()
        ->and(InsuranceCardNumber::isCurrent($child->card_number))->toBeTrue()
        ->and($spouse->card_number)->not->toBe($employeeNumber)
        ->and($child->card_number)->not->toBe($employeeNumber)
        ->and($child->card_number)->not->toBe($spouse->card_number)
        ->and(str_starts_with($spouse->card_number, $employeePrefix))->toBeFalse()
        ->and(str_starts_with($child->card_number, $employeePrefix))->toBeFalse();
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

it('gives a different family card number when the same person belongs to another employee', function () {
    $first = Beneficiary::factory()->create([
        'national_id' => '219880112233',
    ]);
    $second = Beneficiary::factory()->create([
        'national_id' => '219880112233',
    ]);

    expect($second->card_number)->not->toBe($first->card_number);
});
