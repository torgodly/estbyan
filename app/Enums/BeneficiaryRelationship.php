<?php

namespace App\Enums;

enum BeneficiaryRelationship: string
{
    case Spouse = 'spouse';
    case Son = 'son';
    case Daughter = 'daughter';

    public function label(): string
    {
        return match ($this) {
            self::Spouse => 'زوج / زوجة',
            self::Son => 'ابن',
            self::Daughter => 'ابنة',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Spouse => '💑',
            self::Son => '👦',
            self::Daughter => '👧',
        };
    }
}
