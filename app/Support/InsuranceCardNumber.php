<?php

namespace App\Support;

use DateTimeInterface;
use InvalidArgumentException;

final class InsuranceCardNumber
{
    public const DISPLAY_PREFIX = 'SC-';

    public const LENGTH = 8;

    public const STEM_LENGTH = 6;

    public static function isValid(?string $number): bool
    {
        return is_string($number) && preg_match('/^\d{8}$/', $number) === 1;
    }

    public static function normalize(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return self::isValid($digits) ? $digits : null;
    }

    public static function display(?string $number): string
    {
        $digits = self::normalize($number);

        return $digits === null ? '—' : self::DISPLAY_PREFIX.$digits;
    }

    public static function compose(int|string $stem, int $memberIndex): string
    {
        if ($memberIndex < 0 || $memberIndex > 99) {
            throw new InvalidArgumentException('Family card index must be between 0 and 99.');
        }

        $stemInt = (int) (preg_replace('/\D+/', '', (string) $stem) ?? '');

        if ($stemInt < 1 || $stemInt > 999999) {
            throw new InvalidArgumentException('Insurance card stem must be between 1 and 999999.');
        }

        return sprintf('%06d%02d', $stemInt, $memberIndex);
    }

    public static function stem(string $number): string
    {
        return substr(self::validated($number), 0, self::STEM_LENGTH);
    }

    public static function memberIndex(string $number): int
    {
        return (int) substr(self::validated($number), self::STEM_LENGTH, 2);
    }

    public static function employeeNumberFromFamily(string $number): string
    {
        return self::compose(self::stem($number), 0);
    }

    public static function isEmployeeNumber(?string $number): bool
    {
        return self::isValid($number) && self::memberIndex($number) === 0;
    }

    public static function identityKey(
        ?string $nationalId,
        ?string $passportNumber,
        ?string $fullName = null,
        DateTimeInterface|string|null $dateOfBirth = null,
    ): string {
        if (filled($nationalId)) {
            return 'nid:'.trim($nationalId);
        }

        if (filled($passportNumber)) {
            return 'ppt:'.strtoupper(trim($passportNumber));
        }

        $date = $dateOfBirth instanceof DateTimeInterface
            ? $dateOfBirth->format('Y-m-d')
            : trim((string) $dateOfBirth);

        return 'name:'.mb_strtolower(trim((string) $fullName)).'|'.$date;
    }

    private static function validated(string $number): string
    {
        $digits = self::normalize($number);

        if ($digits === null) {
            throw new InvalidArgumentException('Invalid insurance card number.');
        }

        return $digits;
    }
}
