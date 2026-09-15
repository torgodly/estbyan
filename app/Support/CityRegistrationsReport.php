<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;

class CityRegistrationsReport
{
    /**
     * @return array{
     *     totals: array<string, int>,
     *     cities: list<array{key: string, label: string, counts: array<string, int>, total: int}>,
     *     statuses: list<array{value: string, label: string, color: string}>
     * }
     */
    public static function build(): array
    {
        $emptyCounts = self::emptyCounts();
        $cities = [];

        foreach (config('registration.cities') as $key => $label) {
            $cities[$key] = [
                'key' => $key,
                'label' => $label,
                'counts' => $emptyCounts,
                'total' => 0,
            ];
        }

        $rows = MedicalRegistration::query()
            ->selectRaw('city, status, COUNT(*) as aggregate')
            ->groupBy('city', 'status')
            ->get();

        foreach ($rows as $row) {
            $cityKey = filled($row->city) ? (string) $row->city : '';

            if (! isset($cities[$cityKey])) {
                $cities[$cityKey] = [
                    'key' => $cityKey,
                    'label' => $cityKey === '' ? 'غير محددة' : $cityKey,
                    'counts' => $emptyCounts,
                    'total' => 0,
                ];
            }

            $status = $row->status instanceof RegistrationStatus
                ? $row->status->value
                : (string) $row->status;

            $count = (int) $row->aggregate;
            $cities[$cityKey]['counts'][$status] = ($cities[$cityKey]['counts'][$status] ?? 0) + $count;
            $cities[$cityKey]['total'] += $count;
        }

        uasort($cities, function (array $left, array $right): int {
            return $right['total'] <=> $left['total']
                ?: strcmp($left['label'], $right['label']);
        });

        $totals = $emptyCounts;
        $totals['all'] = 0;

        foreach ($cities as $city) {
            foreach ($city['counts'] as $status => $count) {
                $totals[$status] += $count;
                $totals['all'] += $count;
            }
        }

        return [
            'totals' => $totals,
            'cities' => array_values($cities),
            'statuses' => array_map(
                fn (RegistrationStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'color' => $status->color(),
                ],
                RegistrationStatus::cases(),
            ),
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function emptyCounts(): array
    {
        return array_fill_keys(
            array_map(fn (RegistrationStatus $status): string => $status->value, RegistrationStatus::cases()),
            0,
        );
    }
}
