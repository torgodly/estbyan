<?php

namespace App\Support;

use App\Enums\Gender;
use InvalidArgumentException;

class LibyanNationalId
{
    public const LENGTH = 12;

    public static function isValid(string $nationalId): bool
    {
        if (! preg_match('/^[12]\d{11}$/', $nationalId)) {
            return false;
        }

        $year = self::birthYear($nationalId);

        return $year >= 1920 && $year <= (int) now()->format('Y');
    }

    public static function gender(string $nationalId): Gender
    {
        self::assertValidFormat($nationalId);

        return $nationalId[0] === '1' ? Gender::Male : Gender::Female;
    }

    public static function birthYear(string $nationalId): int
    {
        self::assertValidFormat($nationalId);

        return (int) substr($nationalId, 1, 4);
    }

    public static function matchesDateOfBirth(string $nationalId, string $dateOfBirth): bool
    {
        if (! self::isValid($nationalId)) {
            return false;
        }

        try {
            $year = (int) date('Y', strtotime($dateOfBirth));
        } catch (\Throwable) {
            return false;
        }

        return $year === self::birthYear($nationalId);
    }

    public static function matchesGender(string $nationalId, Gender|string $gender): bool
    {
        if (! self::isValid($nationalId)) {
            return false;
        }

        $expected = self::gender($nationalId);
        $actual = $gender instanceof Gender ? $gender : Gender::from($gender);

        return $expected === $actual;
    }

    public static function generate(Gender $gender = Gender::Male, ?int $birthYear = null): string
    {
        $birthYear ??= (int) fake()->numberBetween(1965, 2004);

        return ($gender === Gender::Male ? '1' : '2')
            .sprintf('%04d', $birthYear)
            .fake()->numerify('#######');
    }

    protected static function assertValidFormat(string $nationalId): void
    {
        if (! preg_match('/^[12]\d{11}$/', $nationalId)) {
            throw new InvalidArgumentException('Invalid Libyan national ID format.');
        }
    }
}
