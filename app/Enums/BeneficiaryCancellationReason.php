<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why a family member record was cancelled instead of deleted.
 *
 * @see docs/cancelled-family-members.md
 */
enum BeneficiaryCancellationReason: string implements HasLabel
{
    case EmployeeDuplicate = 'employee_duplicate';

    public function getLabel(): string
    {
        return match ($this) {
            self::EmployeeDuplicate => 'موظف مسجّل كفرد عائلة',
        };
    }

    public function scanWarning(): string
    {
        return match ($this) {
            self::EmployeeDuplicate => 'هذه البطاقة ملغاة لأن صاحبها موظف وله بطاقة موظف خاصة به. احتفظ بهذه البطاقة ولا تسلّمها.',
        };
    }
}
