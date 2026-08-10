<?php

use App\Enums\BeneficiaryRelationship;
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
