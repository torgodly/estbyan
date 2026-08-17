<?php

namespace App\Support;

class WorkplaceOptions
{
    public static function keyForLabel(string $label): ?string
    {
        $normalized = self::normalize($label);

        foreach (config('registration.workplaces') as $key => $workplaceLabel) {
            if (self::normalize($workplaceLabel) === $normalized) {
                return $key;
            }
        }

        foreach (self::aliases() as $alias => $key) {
            if (self::normalize($alias) === $normalized) {
                return $key;
            }
        }

        return null;
    }

    public static function labelForKey(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return config('registration.workplaces.'.$key) ?? $key;
    }

    /**
     * Resolve a workplace key from the Tax Authority spreadsheet "الإدارة" column.
     */
    public static function keyForSpreadsheetAdmin(string $raw): ?string
    {
        $label = self::cleanSpreadsheetAdmin($raw);

        if ($label === '') {
            return null;
        }

        return self::keyForLabel($label);
    }

    public static function cleanSpreadsheetAdmin(string $raw): string
    {
        $value = trim($raw);
        $value = ltrim($value, "_ \t");
        $value = str_replace("\u{0640}", '', $value); // Arabic tatweel

        return trim($value);
    }

    /**
     * @return array<string, string>
     */
    protected static function aliases(): array
    {
        return [
            'العامة' => 'general_admin',
            'الادارة العامة' => 'general_admin',
            'الإدارة العامة' => 'general_admin',
            'الجفـارة' => 'al_jafara',
            'الجفارة' => 'al_jafara',
            'الشاطيء' => 'al_shati',
            'الشاطئ' => 'al_shati',
            'الشاطي' => 'al_shati',
            'ترهونة ومسـلاته' => 'tarhuna_msallata',
            'ترهونة ومسلاته' => 'tarhuna_msallata',
            'ترهونة ومسلاتة' => 'tarhuna_msallata',
            'زواره' => 'zuwara',
            'زوارة' => 'zuwara',
            'مراده' => 'marada',
            'مرادة' => 'marada',
            'مصراته' => 'misrata',
            'مصراتة' => 'misrata',
            'فرع بني وليد' => 'bani_walid',
            'وادى الشاطئ' => 'al_shati',
            'وادي الشاطئ' => 'al_shati',
            'وادي الحياة' => 'wadi_al_hayat',
            'وادي الاجال' => 'wadi_al_hayat',
            'وادي الأجال' => 'wadi_al_hayat',
            'صبراته / صرمان' => 'sabratha',
            'صبراته صرمان' => 'sabratha',
            'غرب جنوب طرابلس' => 'tripoli',
            'جنوب غرب طرابلس' => 'tripoli',
            'مسلاته' => 'tarhuna_msallata',
            'مسلاتة' => 'tarhuna_msallata',
            'كبار الممولين طرابلس' => 'large_taxpayers_tripoli',
        ];
    }

    protected static function normalize(string $value): string
    {
        $value = self::cleanSpreadsheetAdmin($value);
        $value = str_replace(['أ', 'إ', 'آ'], 'ا', $value);
        $value = str_replace('ى', 'ي', $value);
        $value = str_replace('ة', 'ه', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = str_replace(['/', '\\', '-', '_'], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }
}
