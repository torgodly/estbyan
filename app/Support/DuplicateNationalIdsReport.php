<?php

namespace App\Support;

use App\Models\Beneficiary;
use App\Models\Employee;
use Illuminate\Support\Collection;

class DuplicateNationalIdsReport
{
    /**
     * @return array{
     *     employee_as_beneficiary: list<array<string, mixed>>,
     *     duplicate_beneficiaries: list<array<string, mixed>>,
     *     duplicate_employees: list<array<string, mixed>>,
     * }
     */
    public static function build(): array
    {
        return [
            'employee_as_beneficiary' => self::employeeNationalIdsUsedAsBeneficiaries(),
            'duplicate_beneficiaries' => self::beneficiaryNationalIdsUsedMoreThanOnce(),
            'duplicate_employees' => self::employeeNationalIdsUsedMoreThanOnce(),
        ];
    }

    /**
     * Employee national IDs that also appear as a beneficiary on another registration
     * (husband/wife or other employees adding each other).
     *
     * @return list<array{national_id: string, employee_name: string, employee_number: string, beneficiary_name: string, beneficiary_of: string, beneficiary_of_number: string, relationship: string}>
     */
    public static function employeeNationalIdsUsedAsBeneficiaries(): array
    {
        $employeeNids = Employee::query()
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->pluck('national_id');

        if ($employeeNids->isEmpty()) {
            return [];
        }

        $beneficiaries = Beneficiary::query()
            ->with('medicalRegistration')
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->whereIn('national_id', $employeeNids)
            ->orderBy('national_id')
            ->get();

        $employees = Employee::query()
            ->whereIn('national_id', $beneficiaries->pluck('national_id')->unique()->all())
            ->get()
            ->keyBy('national_id');

        return $beneficiaries
            ->map(function (Beneficiary $beneficiary) use ($employees): ?array {
                $employee = $employees->get((string) $beneficiary->national_id);
                $owner = $beneficiary->medicalRegistration;

                if ($employee === null || $owner === null) {
                    return null;
                }

                return [
                    'national_id' => (string) $beneficiary->national_id,
                    'employee_name' => $employee->full_name,
                    'employee_number' => (string) $employee->employee_number,
                    'beneficiary_name' => $beneficiary->full_name,
                    'beneficiary_of' => $owner->full_name,
                    'beneficiary_of_number' => (string) $owner->employee_number,
                    'relationship' => $beneficiary->relationship?->label() ?? (string) $beneficiary->relationship?->value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{national_id: string, count: int, appearances: list<array{beneficiary_name: string, beneficiary_of: string, beneficiary_of_number: string, relationship: string}>}>
     */
    public static function beneficiaryNationalIdsUsedMoreThanOnce(): array
    {
        $duplicateNids = Beneficiary::query()
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->select('national_id')
            ->groupBy('national_id')
            ->havingRaw('count(*) > 1')
            ->orderBy('national_id')
            ->pluck('national_id');

        if ($duplicateNids->isEmpty()) {
            return [];
        }

        $grouped = Beneficiary::query()
            ->with('medicalRegistration')
            ->whereIn('national_id', $duplicateNids)
            ->orderBy('national_id')
            ->get()
            ->groupBy('national_id');

        return $grouped
            ->map(function (Collection $beneficiaries, string $nationalId): array {
                return [
                    'national_id' => $nationalId,
                    'count' => $beneficiaries->count(),
                    'appearances' => $beneficiaries
                        ->map(fn (Beneficiary $beneficiary): array => [
                            'beneficiary_name' => $beneficiary->full_name,
                            'beneficiary_of' => $beneficiary->medicalRegistration?->full_name ?? '—',
                            'beneficiary_of_number' => (string) ($beneficiary->medicalRegistration?->employee_number ?? ''),
                            'relationship' => $beneficiary->relationship?->label() ?? (string) $beneficiary->relationship?->value,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{national_id: string, count: int, employees: list<array{name: string, employee_number: string}>}>
     */
    public static function employeeNationalIdsUsedMoreThanOnce(): array
    {
        $duplicateNids = Employee::query()
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->select('national_id')
            ->groupBy('national_id')
            ->havingRaw('count(*) > 1')
            ->orderBy('national_id')
            ->pluck('national_id');

        if ($duplicateNids->isEmpty()) {
            return [];
        }

        $grouped = Employee::query()
            ->whereIn('national_id', $duplicateNids)
            ->orderBy('national_id')
            ->get()
            ->groupBy('national_id');

        return $grouped
            ->map(fn (Collection $employees, string $nationalId): array => [
                'national_id' => $nationalId,
                'count' => $employees->count(),
                'employees' => $employees
                    ->map(fn (Employee $employee): array => [
                        'name' => $employee->full_name,
                        'employee_number' => (string) $employee->employee_number,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
