<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintedEmployeesPeriodPdf
{
    public const PAGE_WIDTH = 842;

    public const PAGE_HEIGHT = 595;

    public const IMAGE_WIDTH = 1754;

    public const IMAGE_HEIGHT = 1240;

    public static function binary(CarbonInterface|string $from, CarbonInterface|string $until): string
    {
        $slips = PrintedEmployeesPeriodExport::slips($from, $until);
        $pages = array_chunk($slips, 4);

        if ($pages === []) {
            $pages = [[]];
        }

        $jpegs = [];

        foreach ($pages as $pageSlips) {
            $jpegs[] = self::pageJpeg(array_pad($pageSlips, 4, null));
        }

        return self::pdfFromJpegs($jpegs);
    }

    public static function download(CarbonInterface|string $from, CarbonInterface|string $until): StreamedResponse
    {
        $binary = self::binary($from, $until);
        $fromLabel = Carbon::parse($from, PrintedEmployeesPeriodExport::TIMEZONE)->toDateString();
        $untilLabel = Carbon::parse($until, PrintedEmployeesPeriodExport::TIMEZONE)->toDateString();
        $filename = "قسائم-البطاقات-المطبوعة-{$fromLabel}-إلى-{$untilLabel}.pdf";

        return response()->streamDownload(
            function () use ($binary): void {
                echo $binary;
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * @param  list<array{name: string, workplace: string, cards: int}|null>  $slips
     */
    private static function pageJpeg(array $slips): string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            throw new RuntimeException('PHP GD with FreeType is required to generate the printed cards PDF.');
        }

        $image = imagecreatetruecolor(self::IMAGE_WIDTH, self::IMAGE_HEIGHT);

        if ($image === false) {
            throw new RuntimeException('Unable to create the printed cards PDF page.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $navy = imagecolorallocate($image, 30, 58, 95);
        $slate = imagecolorallocate($image, 71, 85, 105);
        $line = imagecolorallocate($image, 148, 163, 184);

        imagefilledrectangle($image, 0, 0, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, $white);

        $halfW = (int) (self::IMAGE_WIDTH / 2);
        $halfH = (int) (self::IMAGE_HEIGHT / 2);

        self::dashedLine($image, $halfW, 24, $halfW, self::IMAGE_HEIGHT - 24, $line);
        self::dashedLine($image, 24, $halfH, self::IMAGE_WIDTH - 24, $halfH, $line);

        $bold = self::fontPath(['Tajawal-Bold.ttf', 'SomarSans-SemiBold.ttf']);
        $regular = self::fontPath(['Tajawal-Regular.ttf', 'SomarSans-SemiBold.ttf']);

        foreach ($slips as $index => $slip) {
            if ($slip === null) {
                continue;
            }

            $col = $index % 2 === 0 ? 1 : 0;
            $row = $index < 2 ? 0 : 1;
            $x = $col * $halfW;
            $y = $row * $halfH;

            self::drawCentered(
                $image,
                $bold,
                32,
                $navy,
                $x,
                $y + (int) ($halfH * 0.38),
                $halfW,
                $slip['name'],
                $halfW - 80,
            );
            self::drawCentered(
                $image,
                $regular,
                18,
                $slate,
                $x,
                $y + (int) ($halfH * 0.50),
                $halfW,
                PrintedEmployeesPeriodExport::placeLabel(),
                $halfW - 80,
            );
            self::drawCentered(
                $image,
                $bold,
                22,
                $navy,
                $x,
                $y + (int) ($halfH * 0.58),
                $halfW,
                $slip['workplace'],
                $halfW - 80,
            );
            self::drawCentered(
                $image,
                $regular,
                16,
                $slate,
                $x,
                $y + (int) ($halfH * 0.70),
                $halfW,
                'عدد البطاقات',
                $halfW - 80,
            );
            self::drawCentered(
                $image,
                $bold,
                36,
                $navy,
                $x,
                $y + (int) ($halfH * 0.80),
                $halfW,
                (string) $slip['cards'],
                $halfW - 80,
            );
        }

        ob_start();
        imagejpeg($image, quality: 85);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * @param  \GdImage  $image
     */
    private static function drawCentered(
        mixed $image,
        string $font,
        int $size,
        int $color,
        int $cellX,
        int $y,
        int $cellWidth,
        string $text,
        int $maxWidth,
    ): void {
        $prepared = ArabicGdText::forGd($text);

        while ($size >= 14) {
            $box = imagettfbbox($size, 0, $font, $prepared);

            if ($box === false) {
                return;
            }

            $textWidth = abs($box[2] - $box[0]);

            if ($textWidth <= $maxWidth) {
                $x = $cellX + (int) (($cellWidth - $textWidth) / 2);
                imagettftext($image, $size, 0, $x, $y, $color, $font, $prepared);

                return;
            }

            $size--;
        }
    }

    /**
     * @param  \GdImage  $image
     */
    private static function dashedLine(mixed $image, int $x1, int $y1, int $x2, int $y2, int $color): void
    {
        $length = (int) hypot($x2 - $x1, $y2 - $y1);

        if ($length === 0) {
            return;
        }

        $dash = 14;
        $gap = 10;
        $drawn = 0;

        while ($drawn < $length) {
            $start = $drawn;
            $end = min($length, $drawn + $dash);
            $sx = (int) round($x1 + (($x2 - $x1) * ($start / $length)));
            $sy = (int) round($y1 + (($y2 - $y1) * ($start / $length)));
            $ex = (int) round($x1 + (($x2 - $x1) * ($end / $length)));
            $ey = (int) round($y1 + (($y2 - $y1) * ($end / $length)));
            imageline($image, $sx, $sy, $ex, $ey, $color);
            $drawn += $dash + $gap;
        }
    }

    /**
     * @param  list<string>  $filenames
     */
    private static function fontPath(array $filenames): string
    {
        foreach ($filenames as $filename) {
            $path = resource_path('fonts/'.$filename);

            if (is_readable($path)) {
                return $path;
            }
        }

        throw new RuntimeException('Missing Arabic font for the printed cards PDF.');
    }

    /**
     * @param  list<string>  $jpegs
     */
    private static function pdfFromJpegs(array $jpegs): string
    {
        $objects = [
            1 => ['dict' => '<< /Type /Catalog /Pages 2 0 R >>', 'stream' => null],
        ];

        $pageIds = [];
        $nextId = 3;

        foreach ($jpegs as $jpeg) {
            $size = getimagesizefromstring($jpeg);
            $width = $size[0] ?? 1;
            $height = $size[1] ?? 1;
            $imageId = $nextId++;
            $contentId = $nextId++;
            $pageId = $nextId++;
            $pageIds[] = $pageId;
            $content = sprintf("q\n%d 0 0 %d 0 0 cm\n/Img Do\nQ\n", self::PAGE_WIDTH, self::PAGE_HEIGHT);

            $objects[$imageId] = [
                'dict' => sprintf(
                    '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>',
                    $width,
                    $height,
                    strlen($jpeg),
                ),
                'stream' => $jpeg,
            ];
            $objects[$contentId] = [
                'dict' => sprintf('<< /Length %d >>', strlen($content)),
                'stream' => $content,
            ];
            $objects[$pageId] = [
                'dict' => sprintf(
                    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /XObject << /Img %d 0 R >> >> /Contents %d 0 R >>',
                    self::PAGE_WIDTH,
                    self::PAGE_HEIGHT,
                    $imageId,
                    $contentId,
                ),
                'stream' => null,
            ];
        }

        $objects[2] = [
            'dict' => sprintf(
                '<< /Type /Pages /Kids [%s] /Count %d >>',
                implode(' ', array_map(fn (int $id): string => $id.' 0 R', $pageIds)),
                count($pageIds),
            ),
            'stream' => null,
        ];

        ksort($objects);

        $buffer = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($buffer);
            $buffer .= $id." 0 obj\n".$object['dict']."\n";

            if ($object['stream'] !== null) {
                $buffer .= "stream\n".$object['stream']."\nendstream\n";
            }

            $buffer .= "endobj\n";
        }

        $xref = strlen($buffer);
        $size = count($objects) + 1;
        $buffer .= "xref\n0 {$size}\n0000000000 65535 f \n";

        for ($id = 1; $id < $size; $id++) {
            $buffer .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $buffer .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $buffer;
    }
}
