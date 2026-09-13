<?php

namespace App\Enums;

enum UserRole: string
{
    case Hr = 'hr';
    case SmartCare = 'smart_care';
    case Reviewer = 'reviewer';

    public function label(): string
    {
        return match ($this) {
            self::Hr => 'الموارد البشرية',
            self::SmartCare => 'سمارت كير',
            self::Reviewer => 'مراجع',
        };
    }
}
