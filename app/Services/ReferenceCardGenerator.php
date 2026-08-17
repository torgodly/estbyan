<?php

namespace App\Services;

use App\Models\MedicalRegistration;
use App\Support\ArabicGdText;
use RuntimeException;

class ReferenceCardGenerator
{
    public function png(MedicalRegistration $registration): string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            throw new RuntimeException('PHP GD with FreeType is required to generate reference cards.');
        }

        $width = 900;
        $height = 1180;
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('Unable to create reference card image.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $canvas = imagecolorallocate($image, 241, 245, 249);
        $white = imagecolorallocate($image, 255, 255, 255);
        $navy = imagecolorallocate($image, 15, 39, 68);
        $teal = imagecolorallocate($image, 13, 148, 136);
        $tealSoft = imagecolorallocate($image, 240, 253, 250);
        $slate = imagecolorallocate($image, 100, 116, 139);
        $slateSoft = imagecolorallocate($image, 248, 250, 252);
        $ring = imagecolorallocate($image, 226, 232, 240);
        $divider = imagecolorallocate($image, 203, 213, 225);

        imagefilledrectangle($image, 0, 0, $width, $height, $canvas);

        $cardX = 40;
        $cardY = 40;
        $cardW = $width - 80;
        $cardH = $height - 80;
        $this->roundedRect($image, $cardX, $cardY, $cardW, $cardH, 40, $white);
        $this->roundedRectBorder($image, $cardX, $cardY, $cardW, $cardH, 40, $ring, 2);

        $bold = $this->fontPath('Tajawal-Bold.ttf');
        $regular = $this->fontPath('Tajawal-Regular.ttf');

        // Login-style partner logos
        $plateW = 210;
        $plateH = 150;
        $plateGap = 28;
        $pairW = ($plateW * 2) + $plateGap;
        $pairX = (int) (($width - $pairW) / 2);
        $plateY = 88;

        // Matching login markup order in RTL: tax authority on the right, Smart Care on the left.
        $taxX = $pairX + $plateW + $plateGap;
        $smartX = $pairX;

        $this->roundedRect($image, $taxX, $plateY, $plateW, $plateH, 24, $white);
        $this->roundedRectBorder($image, $taxX, $plateY, $plateW, $plateH, 24, $ring, 2);
        $this->drawLogo(
            $image,
            $this->taxLogoPath(),
            $taxX + 18,
            $plateY + 28,
            $plateW - 36,
            $plateH - 56,
        );

        $this->roundedRect($image, $smartX, $plateY, $plateW, $plateH, 24, $navy);
        $this->drawLogo(
            $image,
            public_path('images/brand/smart-care.png'),
            $smartX + 18,
            $plateY + 28,
            $plateW - 36,
            $plateH - 56,
        );

        // Soft divider between plates
        $divX = $pairX + $plateW + (int) ($plateGap / 2);
        imageline($image, $divX, $plateY + 28, $divX, $plateY + $plateH - 28, $divider);

        $this->drawCenteredArabic($image, $regular, 18, $slate, $width, 280, 'مصلحة الضرائب · الرعاية الذكية');
        $this->drawCenteredArabic($image, $bold, 36, $navy, $width, 335, 'بطاقة المراجعة');

        // Reference highlight
        $boxX = 100;
        $boxY = 375;
        $boxW = $width - 200;
        $boxH = 180;
        $this->roundedRect($image, $boxX, $boxY, $boxW, $boxH, 28, $tealSoft);
        $this->roundedRectBorder($image, $boxX, $boxY, $boxW, $boxH, 28, imagecolorallocate($image, 167, 243, 208), 2);

        $this->drawCenteredArabic($image, $bold, 22, $teal, $width, $boxY + 55, 'رقم المرجع');
        $this->drawCenteredText($image, $bold, 46, $navy, $width, $boxY + 120, $registration->reference_number ?? '—');

        // Detail rows as soft panels
        $rowX = 100;
        $rowW = $width - 200;
        $rowH = 96;
        $rows = [
            ['label' => 'اسم الموظف', 'value' => $registration->full_name, 'arabic' => true],
            ['label' => 'الرقم الوطني', 'value' => $registration->national_id, 'arabic' => false],
            ['label' => 'الرقم الوظيفي', 'value' => (string) $registration->employee_number, 'arabic' => false],
        ];

        $rowY = 590;
        foreach ($rows as $row) {
            $this->roundedRect($image, $rowX, $rowY, $rowW, $rowH, 22, $slateSoft);
            $this->drawCenteredArabic($image, $regular, 16, $slate, $width, $rowY + 32, $row['label']);

            if ($row['arabic']) {
                $this->drawCenteredArabic($image, $bold, 28, $navy, $width, $rowY + 72, $row['value']);
            } else {
                $this->drawCenteredText($image, $bold, 28, $navy, $width, $rowY + 72, $row['value']);
            }

            $rowY += $rowH + 18;
        }

        $this->drawCenteredArabic($image, $regular, 17, $teal, $width, 1100, 'احتفظ بهذه البطاقة للمتابعة مع الرعاية الذكية');

        ob_start();
        imagepng($image, quality: 6);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    protected function fontPath(string $filename): string
    {
        $path = resource_path('fonts/'.$filename);

        if (! is_readable($path)) {
            throw new RuntimeException("Missing font file: {$filename}");
        }

        return $path;
    }

    protected function taxLogoPath(): string
    {
        return public_path('images/brand/tax-authority.png');
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawLogo(
        mixed $canvas,
        string $path,
        int $x,
        int $y,
        int $maxWidth,
        int $maxHeight,
        bool $tintNavy = false,
    ): void {
        if (! is_readable($path)) {
            return;
        }

        $logo = $this->loadLogoBitmap($path, $maxWidth, $maxHeight);

        if ($logo === null) {
            return;
        }

        if ($tintNavy) {
            $tinted = $this->recolorOpaquePixels($logo, 15, 39, 68);
            imagedestroy($logo);
            $logo = $tinted;
        }

        $srcW = imagesx($logo);
        $srcH = imagesy($logo);
        $dstX = $x + (int) (($maxWidth - $srcW) / 2);
        $dstY = $y + (int) (($maxHeight - $srcH) / 2);

        imagealphablending($canvas, true);
        imagecopy($canvas, $logo, $dstX, $dstY, 0, 0, $srcW, $srcH);
        imagedestroy($logo);
    }

    /**
     * Load and downscale a logo with the sharpest available filter.
     *
     * @return \GdImage|null
     */
    protected function loadLogoBitmap(string $path, int $maxWidth, int $maxHeight): mixed
    {
        if (class_exists(\Imagick::class)) {
            try {
                $imagick = new \Imagick($path);
                $imagick->setImageFormat('png');
                $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
                $imagick->resizeImage($maxWidth, $maxHeight, \Imagick::FILTER_LANCZOS, 1, true);

                $blob = $imagick->getImageBlob();
                $imagick->clear();
                $logo = @imagecreatefromstring($blob);

                if ($logo !== false) {
                    imagealphablending($logo, false);
                    imagesavealpha($logo, true);

                    return $logo;
                }
            } catch (\Throwable) {
                // Fall through to GD.
            }
        }

        $logo = @imagecreatefrompng($path);

        if ($logo === false) {
            return null;
        }

        imagealphablending($logo, false);
        imagesavealpha($logo, true);

        $srcW = imagesx($logo);
        $srcH = imagesy($logo);
        $scale = min($maxWidth / $srcW, $maxHeight / $srcH, 1.0);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        if ($dstW === $srcW && $dstH === $srcH) {
            return $logo;
        }

        $resized = imagecreatetruecolor($dstW, $dstH);

        if ($resized === false) {
            return $logo;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $clear = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $dstW, $dstH, $clear);
        imagecopyresampled($resized, $logo, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($logo);

        return $resized;
    }

    /**
     * Recolor visible logo pixels to a solid brand color (login-page navy filter equivalent).
     *
     * @param  \GdImage  $source
     * @return \GdImage
     */
    protected function recolorOpaquePixels(mixed $source, int $red, int $green, int $blue): mixed
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $dest = imagecreatetruecolor($width, $height);

        if ($dest === false) {
            return $source;
        }

        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $clear = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefilledrectangle($dest, 0, 0, $width, $height, $clear);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($source, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;

                if ($alpha >= 120) {
                    continue;
                }

                $color = imagecolorallocatealpha($dest, $red, $green, $blue, $alpha);
                imagesetpixel($dest, $x, $y, $color);
            }
        }

        return $dest;
    }

    /**
     * @param  \GdImage  $image
     */
    protected function drawCenteredArabic(mixed $image, string $font, int $size, int $color, int $canvasWidth, int $y, string $text): void
    {
        $this->drawCenteredText($image, $font, $size, $color, $canvasWidth, $y, ArabicGdText::forGd($text));
    }

    /**
     * @param  \GdImage  $image
     */
    protected function drawCenteredText(mixed $image, string $font, int $size, int $color, int $canvasWidth, int $y, string $text): void
    {
        $box = imagettfbbox($size, 0, $font, $text);

        if ($box === false) {
            return;
        }

        $textWidth = abs($box[2] - $box[0]);
        $x = (int) (($canvasWidth - $textWidth) / 2);

        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    /**
     * @param  \GdImage  $image
     */
    protected function roundedRect(mixed $image, int $x, int $y, int $w, int $h, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x + $radius, $y, $x + $w - $radius, $y + $h, $color);
        imagefilledrectangle($image, $x, $y + $radius, $x + $w, $y + $h - $radius, $color);
        imagefilledellipse($image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $w - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $radius, $y + $h - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $w - $radius, $y + $h - $radius, $radius * 2, $radius * 2, $color);
    }

    /**
     * @param  \GdImage  $image
     */
    protected function roundedRectBorder(mixed $image, int $x, int $y, int $w, int $h, int $radius, int $color, int $thickness): void
    {
        for ($i = 0; $i < $thickness; $i++) {
            $this->drawRoundedRectOutline(
                $image,
                $x + $i,
                $y + $i,
                $w - ($i * 2),
                $h - ($i * 2),
                max(1, $radius - $i),
                $color,
            );
        }
    }

    /**
     * @param  \GdImage  $image
     */
    protected function drawRoundedRectOutline(mixed $image, int $x, int $y, int $w, int $h, int $radius, int $color): void
    {
        imageline($image, $x + $radius, $y, $x + $w - $radius, $y, $color);
        imageline($image, $x + $radius, $y + $h, $x + $w - $radius, $y + $h, $color);
        imageline($image, $x, $y + $radius, $x, $y + $h - $radius, $color);
        imageline($image, $x + $w, $y + $radius, $x + $w, $y + $h - $radius, $color);
        imagearc($image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, 180, 270, $color);
        imagearc($image, $x + $w - $radius, $y + $radius, $radius * 2, $radius * 2, 270, 360, $color);
        imagearc($image, $x + $radius, $y + $h - $radius, $radius * 2, $radius * 2, 90, 180, $color);
        imagearc($image, $x + $w - $radius, $y + $h - $radius, $radius * 2, $radius * 2, 0, 90, $color);
    }
}
