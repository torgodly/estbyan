<?php

namespace App\Enums;

enum BloodType: string
{
    case APositive = 'a_positive';
    case ANegative = 'a_negative';
    case OPositive = 'o_positive';
    case ONegative = 'o_negative';
    case BPositive = 'b_positive';
    case BNegative = 'b_negative';
    case ABPositive = 'ab_positive';
    case ABNegative = 'ab_negative';

    public function label(): string
    {
        return match ($this) {
            self::APositive => 'A موجب',
            self::ANegative => 'A سالب',
            self::OPositive => 'O موجب',
            self::ONegative => 'O سالب',
            self::BPositive => 'B موجب',
            self::BNegative => 'B سالب',
            self::ABPositive => 'AB موجب',
            self::ABNegative => 'AB سالب',
        };
    }
}
