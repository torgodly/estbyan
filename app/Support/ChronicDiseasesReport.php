<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;

class ChronicDiseasesReport
{
    /**
     * @return array{
     *     registered_employees: int,
     *     registered_people: int,
     *     employees_with_chronic: int,
     *     family_members: int,
     *     family_with_chronic: int,
     *     total_with_chronic: int,
     *     conditions: list<array{
     *         key: string,
     *         label: string,
     *         employees: int,
     *         family: int,
     *         total: int,
     *         employee_share: float,
     *         family_share: float,
     *         share: float
     *     }>
     * }
     */
    public static function build(): array
    {
        $labels = config('registration.chronic_conditions', []);
        $allowed = array_keys($labels);

        $registrations = MedicalRegistration::query()
            ->with('beneficiaries')
            ->where('status', '!=', RegistrationStatus::Draft)
            ->orderBy('full_name')
            ->get();

        $buckets = [];

        foreach ($allowed as $key) {
            $buckets[$key] = [
                'key' => $key,
                'label' => $labels[$key],
                'employees' => 0,
                'family' => 0,
                'total' => 0,
                'employee_share' => 0.0,
                'family_share' => 0.0,
                'share' => 0.0,
            ];
        }

        $employeesWithChronic = 0;
        $familyMembers = 0;
        $familyWithChronic = 0;

        foreach ($registrations as $registration) {
            $employeeConditions = self::sanitize($registration->chronic_conditions ?? [], $allowed);

            if ($registration->has_chronic_conditions || $employeeConditions !== []) {
                $employeesWithChronic++;
            }

            foreach ($employeeConditions as $key) {
                $buckets[$key]['employees']++;
                $buckets[$key]['total']++;
            }

            foreach ($registration->beneficiaries as $beneficiary) {
                $familyMembers++;
                $beneficiaryConditions = self::sanitize($beneficiary->chronic_conditions ?? [], $allowed);
                $hasChronic = $beneficiary->has_chronic_conditions
                    || $beneficiary->has_chronic_condition
                    || $beneficiaryConditions !== [];

                if ($hasChronic) {
                    $familyWithChronic++;
                }

                foreach ($beneficiaryConditions as $key) {
                    $buckets[$key]['family']++;
                    $buckets[$key]['total']++;
                }
            }
        }

        $registeredEmployees = $registrations->count();
        $registeredPeople = $registeredEmployees + $familyMembers;

        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['employee_share'] = self::share($bucket['employees'], $registeredEmployees);
            $buckets[$key]['family_share'] = self::share($bucket['family'], $familyMembers);
            $buckets[$key]['share'] = self::share($bucket['total'], $registeredPeople);
        }

        return [
            'registered_employees' => $registeredEmployees,
            'registered_people' => $registeredPeople,
            'employees_with_chronic' => $employeesWithChronic,
            'family_members' => $familyMembers,
            'family_with_chronic' => $familyWithChronic,
            'total_with_chronic' => $employeesWithChronic + $familyWithChronic,
            'conditions' => array_values($buckets),
        ];
    }

    /**
     * @param  list<string>|null  $conditions
     * @param  list<string>  $allowed
     * @return list<string>
     */
    protected static function sanitize(?array $conditions, array $allowed): array
    {
        return array_values(array_intersect($conditions ?? [], $allowed));
    }

    protected static function share(int $part, int $whole): float
    {
        if ($whole === 0) {
            return 0.0;
        }

        return round(($part / $whole) * 100, 1);
    }
}
