<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

final class InsuranceCardNumber
{
    public const DISPLAY_PREFIX = 'SC-';

    public const LENGTH = 8;

    public const MIN = '10000000';

    public const MAX = '99999999';

    public static function isValid(?string $number): bool
    {
        return is_string($number) && preg_match('/^\d{8}$/', $number) === 1;
    }

    public static function isCurrent(?string $number): bool
    {
        return self::isValid($number) && ! str_starts_with($number, '0');
    }

    public static function needsAssignment(?string $number): bool
    {
        return ! self::isCurrent($number);
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

    /**
     * @param  Builder<*>  $query
     */
    public static function constrainNeedsAssignment(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('card_number')
                ->orWhere('card_number', '<', self::MIN);
        });
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
}
