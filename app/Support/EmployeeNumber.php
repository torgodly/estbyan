<?php

namespace App\Support;

use App\Models\Employee;

class EmployeeNumber
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return str_pad($value, 6, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    public static function next(): string
    {
        $candidate = self::highestAllocated() + 1;

        while (true) {
            $number = str_pad((string) $candidate, 6, '0', STR_PAD_LEFT);
            $candidate++;

            if (in_array($number, TestEmployees::employeeNumbers(), true)) {
                continue;
            }

            if (! self::isTaken($number)) {
                return $number;
            }
        }
    }

    public static function isTaken(string $number): bool
    {
        return Employee::query()
            ->where(function ($query) use ($number): void {
                $query->where('employee_number', $number);

                if (ctype_digit($number)) {
                    $query->orWhere('employee_number', (string) (int) $number);
                }
            })
            ->exists();
    }

    private static function highestAllocated(): int
    {
        $max = 0;

        foreach (Employee::query()->pluck('employee_number') as $number) {
            if (! is_string($number) || ! ctype_digit($number) || strlen($number) !== 6) {
                continue;
            }

            $value = (int) $number;

            if ($value >= 900000) {
                continue;
            }

            $max = max($max, $value);
        }

        return $max;
    }
}
