<?php

use App\Enums\Gender;
use App\Support\LibyanNationalId;
use Tests\TestCase;

uses(TestCase::class);

it('accepts a valid twelve-digit national id', function () {
    expect(LibyanNationalId::isValid('120020129499'))->toBeTrue()
        ->and(LibyanNationalId::gender('120020129499'))->toBe(Gender::Male)
        ->and(LibyanNationalId::birthYear('120020129499'))->toBe(2002);
});

it('rejects wrong length or gender digit', function () {
    expect(LibyanNationalId::isValid('12002012949'))->toBeFalse()
        ->and(LibyanNationalId::isValid('320020129499'))->toBeFalse()
        ->and(LibyanNationalId::isValid('1200a0129499'))->toBeFalse();
});

it('matches date of birth year and gender', function () {
    expect(LibyanNationalId::matchesDateOfBirth('120020129499', '2002-05-10'))->toBeTrue()
        ->and(LibyanNationalId::matchesDateOfBirth('120020129499', '2001-05-10'))->toBeFalse()
        ->and(LibyanNationalId::matchesGender('120020129499', Gender::Male))->toBeTrue()
        ->and(LibyanNationalId::matchesGender('220020129499', Gender::Female))->toBeTrue()
        ->and(LibyanNationalId::matchesGender('120020129499', Gender::Female))->toBeFalse();
});
