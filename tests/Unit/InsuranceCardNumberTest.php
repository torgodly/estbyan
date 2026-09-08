<?php

use App\Support\InsuranceCardNumber;
use Tests\TestCase;

uses(TestCase::class);

it('displays an eight-digit card number with the SC prefix', function () {
    expect(InsuranceCardNumber::display('15893427'))->toBe('SC-15893427')
        ->and(InsuranceCardNumber::display('SC-58473921'))->toBe('SC-58473921')
        ->and(InsuranceCardNumber::display(null))->toBe('—')
        ->and(InsuranceCardNumber::display('SC26-02278'))->toBe('—');
});

it('normalizes printed labels back to the stored digits', function () {
    expect(InsuranceCardNumber::normalize('SC-15893427'))->toBe('15893427')
        ->and(InsuranceCardNumber::normalize('58473921'))->toBe('58473921')
        ->and(InsuranceCardNumber::normalize('SC26-02278'))->toBeNull();
});

it('treats zero-padded family-stem codes as legacy numbers that need upgrading', function () {
    expect(InsuranceCardNumber::isValid('00158900'))->toBeTrue()
        ->and(InsuranceCardNumber::isCurrent('00158900'))->toBeFalse()
        ->and(InsuranceCardNumber::needsAssignment('00158900'))->toBeTrue()
        ->and(InsuranceCardNumber::isCurrent('15893427'))->toBeTrue()
        ->and(InsuranceCardNumber::needsAssignment('15893427'))->toBeFalse()
        ->and(InsuranceCardNumber::needsAssignment(null))->toBeTrue();
});

it('builds an identity key from national id, passport, or name and date of birth', function () {
    expect(InsuranceCardNumber::identityKey('219880112233', null, 'فاطمة', '1988-03-14'))
        ->toBe('nid:219880112233')
        ->and(InsuranceCardNumber::identityKey(null, 'ab123456', 'فاطمة', '1988-03-14'))
        ->toBe('ppt:AB123456')
        ->and(InsuranceCardNumber::identityKey(null, null, 'فاطمة محمد', '1988-03-14'))
        ->toBe('name:فاطمة محمد|1988-03-14');
});
