<?php

namespace App\Support;

use InvalidArgumentException;

final class Code128Barcode
{
    private const START_B = 104;

    private const STOP = 106;

    /**
     * Code 128 bar/space widths for symbols 0-106.
     *
     * @var list<string>
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    public static function svg(string $value, int $barHeight = 56): string
    {
        $symbols = self::symbols($value);
        $moduleWidth = 2;
        $quietZone = 10 * $moduleWidth;
        $x = $quietZone;
        $rects = [];

        foreach ($symbols as $symbol) {
            $pattern = self::PATTERNS[$symbol];
            $drawBar = true;

            foreach (str_split($pattern) as $widthDigit) {
                $width = ((int) $widthDigit) * $moduleWidth;

                if ($drawBar) {
                    $rects[] = '<rect x="'.$x.'" y="0" width="'.$width.'" height="'.$barHeight.'" fill="#000000"/>';
                }

                $x += $width;
                $drawBar = ! $drawBar;
            }
        }

        $width = $x + $quietZone;
        $label = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="'.$label.'" viewBox="0 0 '.$width.' '.$barHeight.'" width="'.$width.'" height="'.$barHeight.'" preserveAspectRatio="none">'
            .implode('', $rects)
            .'</svg>';
    }

    /**
     * @return list<int>
     */
    public static function symbols(string $value): array
    {
        if ($value === '') {
            throw new InvalidArgumentException('Barcode value cannot be empty.');
        }

        $values = [self::START_B];
        $checksum = self::START_B;

        foreach (mb_str_split($value) as $index => $character) {
            $code = self::code128BValue($character);
            $values[] = $code;
            $checksum += $code * ($index + 1);
        }

        $values[] = $checksum % 103;
        $values[] = self::STOP;

        return $values;
    }

    public static function checksum(string $value): int
    {
        $symbols = self::symbols($value);

        return $symbols[count($symbols) - 2];
    }

    private static function code128BValue(string $character): int
    {
        if (strlen($character) !== 1) {
            throw new InvalidArgumentException("Unsupported barcode character [{$character}].");
        }

        $ordinal = ord($character);

        if ($ordinal < 32 || $ordinal > 127) {
            throw new InvalidArgumentException("Unsupported barcode character [{$character}].");
        }

        return $ordinal - 32;
    }
}
