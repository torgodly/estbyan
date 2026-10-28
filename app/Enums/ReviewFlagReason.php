<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Why a person was flagged for a human to review.
 *
 * @see docs/cancelled-family-members.md
 */
enum ReviewFlagReason: string implements HasColor, HasLabel
{
    case PossibleDuplicateEmployee = 'possible_duplicate_employee';
    case PossibleEmployeeAsFamily = 'possible_employee_as_family';
    case PossibleDuplicateFamilyMember = 'possible_duplicate_family_member';
    case EmployeeNationalIdNameMismatch = 'employee_national_id_name_mismatch';

    public function getLabel(): string
    {
        return match ($this) {
            self::PossibleDuplicateEmployee => 'موظف مسجّل مرتين على الأرجح',
            self::PossibleEmployeeAsFamily => 'موظف ربما مسجّل كفرد عائلة',
            self::PossibleDuplicateFamilyMember => 'فرد عائلة ربما مسجّل مرتين',
            self::EmployeeNationalIdNameMismatch => 'رقم وطني لموظف لكن الاسم مختلف',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PossibleDuplicateEmployee, self::EmployeeNationalIdNameMismatch => 'danger',
            default => 'warning',
        };
    }
}
