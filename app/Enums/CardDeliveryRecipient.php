<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum CardDeliveryRecipient: string implements HasDescription, HasLabel
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

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Employee => 'سُلّمت كل البطاقات إلى الموظف شخصياً.',
            self::Administration => 'سُلّمت كل البطاقات إلى إدارة الموظف في العمل.',
        };
    }

    public function description(): string
    {
        return $this->getDescription() ?? '';
    }

    public function icon(): string
    {
        return match ($this) {
            self::Employee => 'heroicon-o-user',
            self::Administration => 'heroicon-o-building-office-2',
        };
    }
}
