<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CardDeliveryRecipient: string implements HasLabel
{
    case Employee = 'employee';
    case Administration = 'administration';

    public function getLabel(): string
    {
        return match ($this) {
            self::Employee => 'الموظف',
            self::Administration => 'الإدارة',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Employee => 'سُلّمت كل البطاقات إلى الموظف شخصياً.',
            self::Administration => 'سُلّمت كل البطاقات إلى إدارة الموظف في العمل.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Employee => 'heroicon-o-user',
            self::Administration => 'heroicon-o-building-office-2',
        };
    }
}
