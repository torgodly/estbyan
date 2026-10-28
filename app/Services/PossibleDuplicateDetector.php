<?php

namespace App\Services;

use App\Enums\ReviewFlagReason;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\ReviewFlag;
use App\Support\PersonName;

/**
 * Finds people who are probably the same person but cannot be merged automatically,
 * because the national IDs differ (typo, swapped digits) or only the name matches.
 * They are flagged for human review; nothing is changed or deleted.
 *
 * Exact matches are handled automatically by {@see DuplicateFamilyMemberCleaner}.
 *
 * @see docs/cancelled-family-members.md
 */
class PossibleDuplicateDetector
{
    /**
     * @return list<array{person: Employee|Beneficiary, reason: ReviewFlagReason, note: string}>
     */
    public function detect(): array
    {
        $people = $this->people();
        $flags = [];

        foreach ($this->candidatePairs($people) as [$left, $right]) {
            foreach ($this->flagsForPair($left, $right) as $flag) {
                $flags[$this->flagKey($flag['person'], $flag['reason'])] = $flag;
            }
        }

        foreach ($this->employeesWithSameNameAsFamilyMember($people) as $flag) {
            $flags[$this->flagKey($flag['person'], $flag['reason'])] ??= $flag;
        }

        return array_values($flags);
    }

    /**
     * @return list<array{person: Employee|Beneficiary, reason: ReviewFlagReason, note: string}>
     */
    public function flag(): array
    {
        $flags = $this->detect();

        foreach ($flags as $flag) {
            ReviewFlag::raise($flag['person'], $flag['reason'], $flag['note']);
        }

        return $flags;
    }

    /**
     * @return list<array{model: Employee|Beneficiary, tokens: list<string>, name: string, national_id: string}>
     */
    private function people(): array
    {
        $people = [];

        foreach (Employee::query()->select(['id', 'employee_number', 'full_name', 'national_id'])->cursor() as $employee) {
            $people[] = $this->person($employee);
        }

        foreach (Beneficiary::query()->with('medicalRegistration.employee:id,employee_number,full_name')->cursor() as $beneficiary) {
            $people[] = $this->person($beneficiary);
        }

        return array_values(array_filter($people, fn (array $person): bool => $person['tokens'] !== []));
    }

    /**
     * @return array{model: Employee|Beneficiary, tokens: list<string>, name: string, national_id: string}
     */
    private function person(Employee|Beneficiary $model): array
    {
        return [
            'model' => $model,
            'tokens' => PersonName::tokens($model->full_name),
            'name' => PersonName::normalize($model->full_name),
            'national_id' => trim((string) $model->national_id),
        ];
    }

    /**
     * Pairs whose national IDs are one typo apart and whose first name matches.
     * Buckets by first name + ID prefix / suffix so only plausible pairs are compared.
     *
     * @param  list<array{model: Employee|Beneficiary, tokens: list<string>, name: string, national_id: string}>  $people
     * @return iterable<array{0: array{model: Employee|Beneficiary, tokens: list<string>, name: string, national_id: string}, 1: array{model: Employee|Beneficiary, tokens: list<string>, name: string, national_id: string}}>
     */
    private function candidatePairs(array $people): iterable
    {
        $buckets = [];

        foreach ($people as $index => $person) {
            if (! preg_match('/^\d{12}$/', $person['national_id'])) {
                continue;
            }

            $first = $person['tokens'][0];
            $buckets['p:'.$first.':'.substr($person['national_id'], 0, 4)][] = $index;
            $buckets['s:'.$first.':'.substr($person['national_id'], 4)][] = $index;
        }

        $seen = [];

        foreach ($buckets as $indexes) {
            $count = count($indexes);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $left = $people[$indexes[$i]];
                    $right = $people[$indexes[$j]];
                    $pairKey = $indexes[$i].':'.$indexes[$j];

                    if (isset($seen[$pairKey]) || ! $this->isOneTypoApart($left['national_id'], $right['national_id'])) {
                        continue;
                    }

                    $seen[$pairKey] = true;

                    yield [$left, $right];
                }
            }
        }
    }

    /**
     * @param  array{model: Employee|Beneficiary, tokens: list<string>, name: string, national_id: string}  $left
     * @param  array{model: Employee|Beneficiary, tokens: list<string>, name: string, national_id: string}  $right
     * @return list<array{person: Employee|Beneficiary, reason: ReviewFlagReason, note: string}>
     */
    private function flagsForPair(array $left, array $right): array
    {
        $sameTwoNames = array_slice($left['tokens'], 0, 2) === array_slice($right['tokens'], 0, 2);

        if ($left['model'] instanceof Employee && $right['model'] instanceof Employee) {
            if (! $sameTwoNames) {
                return [];
            }

            return [
                $this->flagFor($left['model'], ReviewFlagReason::PossibleDuplicateEmployee, $right['model']),
                $this->flagFor($right['model'], ReviewFlagReason::PossibleDuplicateEmployee, $left['model']),
            ];
        }

        if ($left['model'] instanceof Beneficiary && $right['model'] instanceof Beneficiary) {
            $sameNameAndBirthDate = $left['name'] === $right['name']
                && $left['model']->date_of_birth !== null
                && $right['model']->date_of_birth !== null
                && $left['model']->date_of_birth->isSameDay($right['model']->date_of_birth);

            if ($left['model']->medical_registration_id === $right['model']->medical_registration_id || $sameNameAndBirthDate) {
                return [];
            }

            return [
                $this->flagFor($left['model'], ReviewFlagReason::PossibleDuplicateFamilyMember, $right['model']),
                $this->flagFor($right['model'], ReviewFlagReason::PossibleDuplicateFamilyMember, $left['model']),
            ];
        }

        if (! $sameTwoNames) {
            return [];
        }

        [$employee, $beneficiary] = $left['model'] instanceof Employee
            ? [$left['model'], $right['model']]
            : [$right['model'], $left['model']];

        return [$this->flagFor($beneficiary, ReviewFlagReason::PossibleEmployeeAsFamily, $employee)];
    }

    /**
     * An employee and a family member with exactly the same full name but a different national ID.
     *
     * @param  list<array{model: Employee|Beneficiary, tokens: list<string>, name: string, national_id: string}>  $people
     * @return list<array{person: Employee|Beneficiary, reason: ReviewFlagReason, note: string}>
     */
    private function employeesWithSameNameAsFamilyMember(array $people): array
    {
        $employeesByName = [];

        foreach ($people as $person) {
            if ($person['model'] instanceof Employee && count($person['tokens']) >= 3) {
                $employeesByName[$person['name']][] = $person;
            }
        }

        $flags = [];

        foreach ($people as $person) {
            if (! $person['model'] instanceof Beneficiary) {
                continue;
            }

            foreach ($employeesByName[$person['name']] ?? [] as $employee) {
                if ($employee['national_id'] !== $person['national_id']) {
                    $flags[] = $this->flagFor($person['model'], ReviewFlagReason::PossibleEmployeeAsFamily, $employee['model']);
                }
            }
        }

        return $flags;
    }

    private function isOneTypoApart(string $left, string $right): bool
    {
        if ($left === $right || strlen($left) !== strlen($right)) {
            return false;
        }

        $differences = [];

        for ($position = 0; $position < strlen($left); $position++) {
            if ($left[$position] !== $right[$position]) {
                $differences[] = $position;
            }
        }

        if (count($differences) === 1) {
            return true;
        }

        return count($differences) === 2
            && $differences[1] === $differences[0] + 1
            && $left[$differences[0]] === $right[$differences[1]]
            && $left[$differences[1]] === $right[$differences[0]];
    }

    /**
     * @return array{person: Employee|Beneficiary, reason: ReviewFlagReason, note: string}
     */
    private function flagFor(Employee|Beneficiary $person, ReviewFlagReason $reason, Employee|Beneficiary $other): array
    {
        return [
            'person' => $person,
            'reason' => $reason,
            'note' => sprintf('ربما نفس الشخص: %s.', $this->describe($other)),
        ];
    }

    private function describe(Employee|Beneficiary $person): string
    {
        if ($person instanceof Employee) {
            return sprintf('الموظف "%s" (%s) رقمه الوطني %s', trim((string) $person->full_name), $person->employee_number, $person->national_id);
        }

        return sprintf(
            '"%s" المسجل كفرد عائلة في ملف الموظف %s، رقمه الوطني %s',
            trim((string) $person->full_name),
            $person->medicalRegistration?->employee?->employee_number ?? '—',
            $person->national_id ?: '—',
        );
    }

    private function flagKey(Employee|Beneficiary $person, ReviewFlagReason $reason): string
    {
        return $person->getMorphClass().':'.$person->getKey().':'.$reason->value;
    }
}
