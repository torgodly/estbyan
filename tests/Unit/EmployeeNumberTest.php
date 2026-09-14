<?php

use App\Support\EmployeeNumber;

it('normalizes numeric employee numbers to six digits', function () {
    expect(EmployeeNumber::normalize('28105'))->toBe('028105')
        ->and(EmployeeNumber::normalize('028105'))->toBe('028105')
        ->and(EmployeeNumber::normalize('  7  '))->toBe('000007')
        ->and(EmployeeNumber::normalize(''))->toBeNull()
        ->and(EmployeeNumber::normalize(null))->toBeNull()
        ->and(EmployeeNumber::normalize('AB-12'))->toBe('AB-12');
});
