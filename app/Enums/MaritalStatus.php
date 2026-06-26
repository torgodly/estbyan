<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'أعزب/عزباء',
            self::Married => 'متزوج(ة)',
        };
    }
}
