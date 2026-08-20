<?php

namespace App\Enums;

enum BeneficiaryRelationship: string
{
    case Spouse = 'spouse';
    case Son = 'son';
    case Daughter = 'daughter';
    case Father = 'father';
    case Mother = 'mother';

    public function label(?Gender $employeeGender = null): string
    {
        return match ($this) {
            self::Spouse => match ($employeeGender) {
                Gender::Male => 'زوجة',
                Gender::Female => 'زوج',
                default => 'زوج / زوجة',
            },
            self::Son => 'ابن',
            self::Daughter => 'ابنة',
            self::Father => 'أب',
            self::Mother => 'أم',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Spouse => '💑',
            self::Son => '👦',
            self::Daughter => '👧',
            self::Father => '👨',
            self::Mother => '👩',
        };
    }

    public function expectedGender(?Gender $employeeGender = null): ?Gender
    {
        return match ($this) {
            self::Son, self::Father => Gender::Male,
            self::Daughter, self::Mother => Gender::Female,
            self::Spouse => match ($employeeGender) {
                Gender::Male => Gender::Female,
                Gender::Female => Gender::Male,
                default => null,
            },
        };
    }

    public function requiresMarriedEmployee(): bool
    {
        return match ($this) {
            self::Spouse, self::Son, self::Daughter => true,
            self::Father, self::Mother => false,
        };
    }

    public function isChild(): bool
    {
        return match ($this) {
            self::Son, self::Daughter => true,
            default => false,
        };
    }

    /**
     * Spouse and mother may always be non-Libyan.
     * Children become non-Libyan only when the form forces it (non-Libyan husband).
     */
    public function allowsNonLibyan(): bool
    {
        return match ($this) {
            self::Spouse, self::Mother => true,
            self::Son, self::Daughter, self::Father => false,
        };
    }

    /**
     * Male employees may register up to 4 wives; female employees one husband.
     */
    public static function maxSpousesFor(Gender|string $employeeGender): int
    {
        $gender = $employeeGender instanceof Gender
            ? $employeeGender
            : Gender::from($employeeGender);

        return match ($gender) {
            Gender::Male => 4,
            Gender::Female => 1,
        };
    }

    /**
     * @return list<self>
     */
    public static function availableFor(MaritalStatus|string $maritalStatus): array
    {
        $status = $maritalStatus instanceof MaritalStatus
            ? $maritalStatus
            : MaritalStatus::from($maritalStatus);

        return array_values(array_filter(
            self::cases(),
            fn (self $relationship): bool => ! $relationship->requiresMarriedEmployee()
                || $status === MaritalStatus::Married,
        ));
    }
}
