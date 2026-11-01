<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Libyan mobile number: 10 English digits starting with 091, 092, 093 or 094 (e.g. 0910000000).
 * Arabic-Indic digits (٠١٢...) are rejected, not converted.
 */
class LibyanMobileNumber implements ValidationRule
{
    public const PATTERN = '/^09[1-4]\d{7}$/';

    public const MESSAGE = 'رقم الهاتف يجب أن يتكون من 10 أرقام إنجليزية ويبدأ بـ 091 أو 092 أو 093 أو 094 (مثل 0910000000).';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail(self::MESSAGE);
        }
    }

    /**
     * Remove spaces and dashes and turn the +218 / 00218 country code into 0.
     * Anything else (letters, Arabic-Indic digits) is kept as typed so validation rejects it.
     */
    public static function normalize(?string $value): string
    {
        $value = (string) preg_replace('/[\s\-().]+/u', '', trim((string) $value));

        return (string) preg_replace('/^(?:\+|00)?218(?=9)/', '0', $value);
    }
}
