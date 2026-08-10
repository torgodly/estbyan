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
     * @return array<string, string>
     */
    protected static function aliases(): array
    {
        return [
            'صبراته صرمان' => 'sabratha_sorman',
            'صبراته/صرمان' => 'sabratha_sorman',
            'مسلاتة' => 'msallata',
            'مصراتة' => 'misrata',
            'وادي الشاطئ' => 'wadi_al_shati',
            'وادي الأجال' => 'wadi_al_ajal',
            'جنوب غرب طرابلس' => 'southwest_tripoli',
            'غرب جنوب طرابلس' => 'southwest_tripoli',
            'بني وليد' => 'bani_walid',
            'فرع بني وليد' => 'bani_walid',
        ];
    }

    protected static function normalize(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['أ', 'إ', 'آ'], 'ا', $value);
        $value = str_replace('ى', 'ي', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = str_replace(['/', '\\', '-', '_'], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }
}
