<?php

namespace App\Support;

/**
 * Prepare Arabic text for GD's imagettftext (no native RTL/shaping).
 * Applies presentation-form reshaping, then visual reverse for LTR drawing.
 */
class ArabicGdText
{
    /**
     * Forms: isolated, final, initial, medial. Null means form does not exist.
     *
     * @var array<string, array{0:string,1:string,2:?string,3:?string}>
     */
    protected static array $glyphs = [
        'ء' => ['ء', 'ء', null, null],
        'آ' => ['آ', 'ﺂ', null, null],
        'أ' => ['أ', 'ﺄ', null, null],
        'ؤ' => ['ؤ', 'ﺆ', null, null],
        'إ' => ['إ', 'ﺈ', null, null],
        'ئ' => ['ئ', 'ﺊ', 'ﺋ', 'ﺌ'],
        'ا' => ['ا', 'ﺎ', null, null],
        'ب' => ['ب', 'ﺐ', 'ﺑ', 'ﺒ'],
        'ة' => ['ة', 'ﺔ', null, null],
        'ت' => ['ت', 'ﺖ', 'ﺗ', 'ﺘ'],
        'ث' => ['ث', 'ﺚ', 'ﺛ', 'ﺜ'],
        'ج' => ['ج', 'ﺞ', 'ﺟ', 'ﺠ'],
        'ح' => ['ح', 'ﺢ', 'ﺣ', 'ﺤ'],
        'خ' => ['خ', 'ﺦ', 'ﺧ', 'ﺨ'],
        'د' => ['د', 'ﺪ', null, null],
        'ذ' => ['ذ', 'ﺬ', null, null],
        'ر' => ['ر', 'ﺮ', null, null],
        'ز' => ['ز', 'ﺰ', null, null],
        'س' => ['س', 'ﺲ', 'ﺳ', 'ﺴ'],
        'ش' => ['ش', 'ﺶ', 'ﺷ', 'ﺸ'],
        'ص' => ['ص', 'ﺺ', 'ﺻ', 'ﺼ'],
        'ض' => ['ض', 'ﺾ', 'ﺿ', 'ﻀ'],
        'ط' => ['ط', 'ﻂ', 'ﻃ', 'ﻄ'],
        'ظ' => ['ظ', 'ﻆ', 'ﻇ', 'ﻈ'],
        'ع' => ['ع', 'ﻊ', 'ﻋ', 'ﻌ'],
        'غ' => ['غ', 'ﻎ', 'ﻏ', 'ﻐ'],
        'ف' => ['ف', 'ﻒ', 'ﻓ', 'ﻔ'],
        'ق' => ['ق', 'ﻖ', 'ﻗ', 'ﻘ'],
        'ك' => ['ك', 'ﻚ', 'ﻛ', 'ﻜ'],
        'ل' => ['ل', 'ﻞ', 'ﻟ', 'ﻠ'],
        'م' => ['م', 'ﻢ', 'ﻣ', 'ﻤ'],
        'ن' => ['ن', 'ﻦ', 'ﻧ', 'ﻨ'],
        'ه' => ['ه', 'ﻪ', 'ﻫ', 'ﻬ'],
        'و' => ['و', 'ﻮ', null, null],
        'ى' => ['ى', 'ﻰ', null, null],
        'ي' => ['ي', 'ﻲ', 'ﻳ', 'ﻴ'],
        'لا' => ['لا', 'ﻼ', null, null],
        'لآ' => ['لآ', 'ﻶ', null, null],
        'لأ' => ['لأ', 'ﻸ', null, null],
        'لإ' => ['لإ', 'ﻺ', null, null],
    ];

    public static function forGd(string $text): string
    {
        if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        return self::reverseUtf8(self::reshape($text));
    }

    protected static function reshape(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($chars);
        $output = '';

        for ($i = 0; $i < $count; $i++) {
            $char = $chars[$i];

            if ($char === 'ل' && isset($chars[$i + 1]) && in_array($chars[$i + 1], ['ا', 'أ', 'إ', 'آ'], true)) {
                $ligature = 'ل'.$chars[$i + 1];
                $forms = self::$glyphs[$ligature];
                $prevConnects = $i > 0 && self::connectsToNext($chars[$i - 1]);
                $output .= $forms[$prevConnects ? 1 : 0];
                $i++;

                continue;
            }

            if (! isset(self::$glyphs[$char])) {
                $output .= $char;

                continue;
            }

            $forms = self::$glyphs[$char];
            $prevConnects = $i > 0 && self::connectsToNext($chars[$i - 1]);
            $nextConnects = $i < $count - 1 && self::canReceiveConnection($chars[$i + 1]);

            if ($prevConnects && $nextConnects && $forms[3] !== null) {
                $output .= $forms[3];
            } elseif (! $prevConnects && $nextConnects && $forms[2] !== null) {
                $output .= $forms[2];
            } elseif ($prevConnects && $forms[1] !== null) {
                $output .= $forms[1];
            } else {
                $output .= $forms[0];
            }
        }

        return $output;
    }

    protected static function connectsToNext(string $char): bool
    {
        return isset(self::$glyphs[$char]) && self::$glyphs[$char][2] !== null;
    }

    protected static function canReceiveConnection(string $char): bool
    {
        return isset(self::$glyphs[$char]);
    }

    protected static function reverseUtf8(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_reverse($chars));
    }
}
