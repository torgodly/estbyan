<?php

namespace App\Support;

/**
 * Compare Arabic person names while ignoring spelling variants that do not change the name.
 */
class PersonName
{
    /**
     * Name parts, with hamza forms of alef, ى/ي, ة/ه and diacritics unified, and
     * compound prefixes (عبد / ابو / بن) joined to the next word.
     *
     * @return list<string>
     */
    public static function tokens(?string $name): array
    {
        $name = strtr((string) $name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي']);
        $name = (string) preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $name);
        $name = (string) preg_replace('/(^|\s)(عبد|ابو|بن)\s+/u', '$1$2', $name);

        return array_values(array_filter(preg_split('/\s+/u', trim($name)) ?: []));
    }

    public static function normalize(?string $name): string
    {
        return implode('', self::tokens($name));
    }

    public static function sameFirstName(?string $left, ?string $right): bool
    {
        $leftName = self::normalize($left);
        $rightName = self::normalize($right);
        $leftFirst = self::tokens($left)[0] ?? '';
        $rightFirst = self::tokens($right)[0] ?? '';

        if ($leftFirst === '' || $rightFirst === '') {
            return false;
        }

        return str_starts_with($rightName, $leftFirst) || str_starts_with($leftName, $rightFirst);
    }
}
