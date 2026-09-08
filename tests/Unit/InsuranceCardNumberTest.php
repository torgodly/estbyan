<?php

use App\Support\InsuranceCardNumber;
use Tests\TestCase;

uses(TestCase::class);

it('displays an eight-digit card number with the SC prefix', function () {
    expect(InsuranceCardNumber::display('12345600'))->toBe('SC-12345600')
        ->and(InsuranceCardNumber::display('SC-12345601'))->toBe('SC-12345601')
        ->and(InsuranceCardNumber::display(null))->toBe('—')
        ->and(InsuranceCardNumber::display('SC26-02278'))->toBe('—');
});

it('normalizes printed labels back to the stored digits', function () {
    expect(InsuranceCardNumber::normalize('SC-00000100'))->toBe('00000100')
        ->and(InsuranceCardNumber::normalize('00000101'))->toBe('00000101')
        ->and(InsuranceCardNumber::normalize('SC26-02278'))->toBeNull();
});

it('composes a family code from a stem and member index', function () {
    expect(InsuranceCardNumber::compose(1, 0))->toBe('00000100')
        ->and(InsuranceCardNumber::compose('000001', 2))->toBe('00000102')
        ->and(InsuranceCardNumber::stem('00000102'))->toBe('000001')
        ->and(InsuranceCardNumber::memberIndex('00000102'))->toBe(2)
        ->and(InsuranceCardNumber::employeeNumberFromFamily('00000102'))->toBe('00000100')
        ->and(InsuranceCardNumber::isEmployeeNumber('00000100'))->toBeTrue()
        ->and(InsuranceCardNumber::isEmployeeNumber('00000101'))->toBeFalse();
});

it('builds an identity key from national id, passport, or name and date of birth', function () {
    expect(InsuranceCardNumber::identityKey('219880112233', null, 'فاطمة', '1988-03-14'))
        ->toBe('nid:219880112233')
        ->and(InsuranceCardNumber::identityKey(null, 'ab123456', 'فاطمة', '1988-03-14'))
        ->toBe('ppt:AB123456')
        ->and(InsuranceCardNumber::identityKey(null, null, 'فاطمة محمد', '1988-03-14'))
        ->toBe('name:فاطمة محمد|1988-03-14');
});
