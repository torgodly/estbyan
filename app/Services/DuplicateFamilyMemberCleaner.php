<?php

namespace App\Services;

use App\Enums\BeneficiaryCancellationReason;
use App\Enums\RegistrationStatus;
use App\Enums\ReviewFlagReason;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;
use App\Support\PersonName;
use App\Support\RegistrationDocuments;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Employees registered as someone else's family member are cancelled, not deleted,
 * so their already printed cards are recognised and held back when scanned.
 * Other duplicate family members are deleted. When the national ID matches an
 * employee but the first name does not, nothing is changed and the record is
 * flagged for a human to review instead.
 *
 * @see docs/cancelled-family-members.md
 */
class DuplicateFamilyMemberCleaner
{
    /**
     * @param  list<int>  $exceptIds  Beneficiary IDs to leave untouched (e.g. pending manual review).
     * @return array{
     *     employee_as_beneficiary: int,
     *     duplicate_beneficiaries: int,
     *     registrations_updated: int,
     *     flagged_for_review: list<array{id: int, name: string, employee_name: string}>,
     *     records: list<array{id: int, name: string, national_id: ?string, passport_number: ?string, reason: string, printed: bool, registration_id: ?int, employee_id: ?int}>
     * }
     */
    public function cleanup(bool $dryRun = false, array $exceptIds = []): array
    {
        $employeeNationalIds = $this->employeeNationalIds();

        $matchingEmployees = $this->beneficiariesMatchingEmployees($employeeNationalIds)
            ->reject(fn (Beneficiary $beneficiary): bool => in_array($beneficiary->id, $exceptIds, true));

        $employeesByNationalId = Employee::query()
            ->whereIn('national_id', $matchingEmployees->pluck('national_id')->map(fn (mixed $id): string => trim((string) $id))->all())
            ->get()
            ->keyBy(fn (Employee $employee): string => trim((string) $employee->national_id));

        [$employeeAsBeneficiary, $nameMismatches] = $matchingEmployees->partition(
            fn (Beneficiary $beneficiary): bool => PersonName::sameFirstName(
                $beneficiary->full_name,
                $employeesByNationalId->get(trim((string) $beneficiary->national_id))?->full_name,
            ),
        );
        $employeeAsBeneficiary = $employeeAsBeneficiary->values();

        $flaggedForReview = $nameMismatches->map(fn (Beneficiary $beneficiary): array => [
            'id' => $beneficiary->id,
            'name' => $beneficiary->full_name ?: '—',
            'employee_name' => $employeesByNationalId->get(trim((string) $beneficiary->national_id))?->full_name ?? '—',
        ])->values()->all();
        $duplicates = $this->duplicateBeneficiariesToDelete([...$matchingEmployees->modelKeys(), ...$exceptIds]);

        $employeeAsBeneficiaryIds = array_flip($employeeAsBeneficiary->modelKeys());

        $affected = $employeeAsBeneficiary
            ->concat($duplicates)
            ->unique('id')
            ->values();

        $records = $affected->map(function (Beneficiary $beneficiary) use ($employeeAsBeneficiaryIds): array {
            return [
                'id' => $beneficiary->id,
                'name' => $beneficiary->full_name ?: '—',
                'national_id' => $beneficiary->national_id,
                'passport_number' => $beneficiary->passport_number,
                'reason' => array_key_exists($beneficiary->id, $employeeAsBeneficiaryIds)
                    ? 'employee_as_beneficiary'
                    : 'duplicate_beneficiary',
                'printed' => $beneficiary->card_printed_at !== null,
                'registration_id' => $beneficiary->medical_registration_id,
                'employee_id' => $beneficiary->medicalRegistration?->employee_id,
            ];
        })->all();

        if (! $dryRun) {
            foreach ($nameMismatches as $beneficiary) {
                ReviewFlag::raise(
                    $beneficiary,
                    ReviewFlagReason::EmployeeNationalIdNameMismatch,
                    sprintf(
                        'الرقم الوطني %s يخص الموظف "%s" لكن الاسم المسجل هنا "%s".',
                        $beneficiary->national_id,
                        $employeesByNationalId->get(trim((string) $beneficiary->national_id))?->full_name ?? '—',
                        trim((string) $beneficiary->full_name),
                    ),
                );
            }
        }

        if ($dryRun || $affected->isEmpty()) {
            return [
                'employee_as_beneficiary' => $employeeAsBeneficiary->count(),
                'duplicate_beneficiaries' => $duplicates->count(),
                'registrations_updated' => 0,
                'flagged_for_review' => $flaggedForReview,
                'records' => $records,
            ];
        }

        $registrationIds = $affected
            ->pluck('medical_registration_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($employeeAsBeneficiary, $duplicates): void {
            foreach ($employeeAsBeneficiary as $beneficiary) {
                $beneficiary->cancel(BeneficiaryCancellationReason::EmployeeDuplicate);
            }

            foreach ($duplicates as $beneficiary) {
                $this->deletePhoto($beneficiary);
                $beneficiary->delete();
            }
        });

        $registrationsUpdated = 0;

        foreach (MedicalRegistration::query()->whereKey($registrationIds)->cursor() as $registration) {
            $registration->forceFill([
                'beneficiaries_count' => $registration->beneficiaries()->count(),
            ])->saveQuietly();

            $registrationsUpdated++;
        }

        return [
            'employee_as_beneficiary' => $employeeAsBeneficiary->count(),
            'duplicate_beneficiaries' => $duplicates->count(),
            'registrations_updated' => $registrationsUpdated,
            'flagged_for_review' => $flaggedForReview,
            'records' => $records,
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function employeeNationalIds(): Collection
    {
        return Employee::query()
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->pluck('national_id')
            ->map(fn (mixed $nationalId): string => trim((string) $nationalId))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, string>  $employeeNationalIds
     * @return Collection<int, Beneficiary>
     */
    private function beneficiariesMatchingEmployees(Collection $employeeNationalIds): Collection
    {
        if ($employeeNationalIds->isEmpty()) {
            return collect();
        }

        $lookup = $employeeNationalIds->flip();

        return Beneficiary::query()
            ->with('medicalRegistration')
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->orderBy('id')
            ->get()
            ->filter(fn (Beneficiary $beneficiary): bool => $lookup->has(trim((string) $beneficiary->national_id)))
            ->values();
    }

    /**
     * The same person is matched by national ID / passport first, then by
     * full name + date of birth. The second pass catches a child added under
     * both parents with a mistyped or fake national ID.
     *
     * @param  list<int>  $alreadyRemovingIds
     * @return Collection<int, Beneficiary>
     */
    private function duplicateBeneficiariesToDelete(array $alreadyRemovingIds): Collection
    {
        $beneficiaries = Beneficiary::query()
            ->with('medicalRegistration')
            ->when($alreadyRemovingIds !== [], fn ($query) => $query->whereKeyNot($alreadyRemovingIds))
            ->orderBy('id')
            ->get();

        $byIdentity = $this->duplicatesWithinGroups($beneficiaries, fn (Beneficiary $beneficiary): ?string => $this->identityKey($beneficiary));

        $byNameAndBirthDate = $this->duplicatesWithinGroups(
            $beneficiaries->whereNotIn('id', $byIdentity->pluck('id')->all()),
            fn (Beneficiary $beneficiary): ?string => $this->nameAndBirthDateKey($beneficiary),
        );

        return $byIdentity->concat($byNameAndBirthDate)->values();
    }

    /**
     * @param  Collection<int, Beneficiary>  $beneficiaries
     * @param  callable(Beneficiary): ?string  $keyFor
     * @return Collection<int, Beneficiary>
     */
    private function duplicatesWithinGroups(Collection $beneficiaries, callable $keyFor): Collection
    {
        return $beneficiaries
            ->groupBy(fn (Beneficiary $beneficiary): string => $keyFor($beneficiary) ?? 'skip:'.$beneficiary->id)
            ->filter(fn (Collection $group, string $key): bool => ! str_starts_with($key, 'skip:') && $group->count() > 1)
            ->flatMap(function (Collection $group): Collection {
                $keep = $this->preferredBeneficiary($group);

                return $group
                    ->reject(fn (Beneficiary $beneficiary): bool => $beneficiary->id === $keep->id)
                    ->values();
            })
            ->values();
    }

    private function nameAndBirthDateKey(Beneficiary $beneficiary): ?string
    {
        $name = PersonName::normalize($beneficiary->full_name);

        if ($name === '' || $beneficiary->date_of_birth === null) {
            return null;
        }

        return 'name:'.$name.':'.$beneficiary->date_of_birth->format('Y-m-d');
    }

    private function identityKey(Beneficiary $beneficiary): ?string
    {
        if (filled($beneficiary->national_id)) {
            return 'nid:'.trim((string) $beneficiary->national_id);
        }

        if (filled($beneficiary->passport_number)) {
            return 'passport:'
                .trim((string) ($beneficiary->nationality ?? ''))
                .':'
                .trim((string) $beneficiary->passport_number);
        }

        return null;
    }

    /**
     * @param  Collection<int, Beneficiary>  $group
     */
    private function preferredBeneficiary(Collection $group): Beneficiary
    {
        return $group
            ->sort(function (Beneficiary $left, Beneficiary $right): int {
                $leftPrinted = $left->card_printed_at === null ? 1 : 0;
                $rightPrinted = $right->card_printed_at === null ? 1 : 0;

                if ($leftPrinted !== $rightPrinted) {
                    return $leftPrinted <=> $rightPrinted;
                }

                $leftApproved = $left->medicalRegistration?->status === RegistrationStatus::Approved ? 0 : 1;
                $rightApproved = $right->medicalRegistration?->status === RegistrationStatus::Approved ? 0 : 1;

                if ($leftApproved !== $rightApproved) {
                    return $leftApproved <=> $rightApproved;
                }

                return $left->id <=> $right->id;
            })
            ->first();
    }

    private function deletePhoto(Beneficiary $beneficiary): void
    {
        $path = $beneficiary->photo_path;

        if (! filled($path)) {
            return;
        }

        $disk = RegistrationDocuments::disk();

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
