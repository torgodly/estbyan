<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Tests\TestCase;

uses(TestCase::class);

it('allows parents for single employees and full family for married employees', function () {
    $single = array_map(
        fn (BeneficiaryRelationship $r) => $r->value,
        BeneficiaryRelationship::availableFor(MaritalStatus::Single),
    );

    $married = array_map(
        fn (BeneficiaryRelationship $r) => $r->value,
        BeneficiaryRelationship::availableFor(MaritalStatus::Married),
    );

    expect($single)->toBe(['father', 'mother'])
        ->and($married)->toBe(['spouse', 'son', 'daughter', 'father', 'mother']);
});

it('marks spouse and mother as allowed to be non-libyan', function () {
    expect(BeneficiaryRelationship::Spouse->allowsNonLibyan())->toBeTrue()
        ->and(BeneficiaryRelationship::Mother->allowsNonLibyan())->toBeTrue()
        ->and(BeneficiaryRelationship::Father->allowsNonLibyan())->toBeFalse()
        ->and(BeneficiaryRelationship::Son->allowsNonLibyan())->toBeFalse()
        ->and(BeneficiaryRelationship::Daughter->allowsNonLibyan())->toBeFalse();
});

it('identifies son and daughter as children', function () {
    expect(BeneficiaryRelationship::Son->isChild())->toBeTrue()
        ->and(BeneficiaryRelationship::Daughter->isChild())->toBeTrue()
        ->and(BeneficiaryRelationship::Spouse->isChild())->toBeFalse()
        ->and(BeneficiaryRelationship::Father->isChild())->toBeFalse();
});

it('labels spouse as wife for male employees and husband for female employees', function () {
    expect(BeneficiaryRelationship::Spouse->label(Gender::Male))->toBe('زوجة')
        ->and(BeneficiaryRelationship::Spouse->label(Gender::Female))->toBe('زوج')
        ->and(BeneficiaryRelationship::Spouse->label())->toBe('زوج / زوجة');
});

it('expects opposite gender for the spouse based on the employee', function () {
    expect(BeneficiaryRelationship::Spouse->expectedGender(Gender::Male))->toBe(Gender::Female)
        ->and(BeneficiaryRelationship::Spouse->expectedGender(Gender::Female))->toBe(Gender::Male)
        ->and(BeneficiaryRelationship::Spouse->expectedGender())->toBeNull();
});

it('limits male employees to four spouses and female employees to one', function () {
    expect(BeneficiaryRelationship::maxSpousesFor(Gender::Male))->toBe(4)
        ->and(BeneficiaryRelationship::maxSpousesFor(Gender::Female))->toBe(1)
        ->and(BeneficiaryRelationship::maxSpousesFor('male'))->toBe(4);
});

it('prioritizes neighboring nationalities before alphabetical countries', function () {
    $priority = config('registration.nationality_priority');
    $nationalities = config('registration.nationalities');

    expect($priority[0])->toBe('egyptian')
        ->and($priority[1])->toBe('tunisian')
        ->and($priority)->toContain('chadian')
        ->and($nationalities)->toHaveKey('egyptian')
        ->and($nationalities)->toHaveKey('other')
        ->and(count($nationalities))->toBeGreaterThan(80);
});
