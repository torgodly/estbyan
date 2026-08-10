<?php

namespace App\Enums;

enum BeneficiaryRelationship: string
{
    case Spouse = 'spouse';
    case Son = 'son';
    case Daughter = 'daughter';
    case Father = 'father';
    case Mother = 'mother';

    public function label(): string
    {
        return match ($this) {
            self::Spouse => 'زوج / زوجة',
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

    public function expectedGender(): ?Gender
    {
        return match ($this) {
            self::Son, self::Father => Gender::Male,
            self::Daughter, self::Mother => Gender::Female,
            self::Spouse => null,
        };
    }

    public function requiresMarriedEmployee(): bool
    {
        return match ($this) {
            self::Spouse, self::Son, self::Daughter => true,
            self::Father, self::Mother => false,
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
