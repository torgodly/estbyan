<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Submitted => 'مُرسَل',
            self::Approved => 'مقبول',
            self::Declined => 'مرفوض',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Approved => 'success',
            self::Declined => 'danger',
        };
    }

    public function isEditableByEmployee(): bool
    {
        return match ($this) {
            self::Draft, self::Submitted, self::Declined => true,
            self::Approved => false,
        };
    }
}
