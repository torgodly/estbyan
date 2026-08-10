<?php

namespace App\Rules;

use App\Support\LibyanNationalId as LibyanNationalIdSupport;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LibyanNationalId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('الرقم الوطني غير صالح.');

            return;
        }

        $nationalId = (string) $value;

        if (strlen($nationalId) !== LibyanNationalIdSupport::LENGTH) {
            $fail('الرقم الوطني يجب أن يتكون من 12 رقماً.');

            return;
        }

        if (! preg_match('/^\d{12}$/', $nationalId)) {
            $fail('الرقم الوطني يجب أن يحتوي على أرقام فقط.');

            return;
        }

        if (! in_array($nationalId[0], ['1', '2'], true)) {
            $fail('الرقم الوطني يجب أن يبدأ بـ 1 للذكر أو 2 للأنثى.');

            return;
        }

        if (! LibyanNationalIdSupport::isValid($nationalId)) {
            $fail('سنة الميلاد في الرقم الوطني غير صحيحة.');
        }
    }
}
