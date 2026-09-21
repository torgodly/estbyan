<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\RegistrationDocuments;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DuplicateFamilyMemberCleaner
{
    /**
     * @return array{
     *     employee_as_beneficiary: int,
     *     duplicate_beneficiaries: int,
     *     registrations_updated: int,
     *     deleted: list<array{id: int, name: string, national_id: ?string, passport_number: ?string, reason: string, registration_id: ?int, employee_id: ?int}>
     * }
     */
    public function cleanup(bool $dryRun = false): array
    {
        $employeeNationalIds = $this->employeeNationalIds();

        $employeeAsBeneficiary = $this->beneficiariesMatchingEmployees($employeeNationalIds);
        $duplicates = $this->duplicateBeneficiariesToDelete($employeeAsBeneficiary->modelKeys());

        $employeeAsBeneficiaryIds = array_flip($employeeAsBeneficiary->modelKeys());

        $toDelete = $employeeAsBeneficiary
            ->concat($duplicates)
            ->unique('id')
            ->values();

        $deleted = $toDelete->map(function (Beneficiary $beneficiary) use ($employeeAsBeneficiaryIds): array {
            return [
                'id' => $beneficiary->id,
                'name' => $beneficiary->full_name ?: '—',
                'national_id' => $beneficiary->national_id,
                'passport_number' => $beneficiary->passport_number,
                'reason' => array_key_exists($beneficiary->id, $employeeAsBeneficiaryIds)
                    ? 'employee_as_beneficiary'
                    : 'duplicate_beneficiary',
                'registration_id' => $beneficiary->medical_registration_id,
                'employee_id' => $beneficiary->medicalRegistration?->employee_id,
            ];
        })->all();

        if ($dryRun || $toDelete->isEmpty()) {
            return [
                'employee_as_beneficiary' => $employeeAsBeneficiary->count(),
                'duplicate_beneficiaries' => $duplicates->count(),
                'registrations_updated' => 0,
                'deleted' => $deleted,
            ];
        }

        $registrationIds = $toDelete
            ->pluck('medical_registration_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($toDelete): void {
            foreach ($toDelete as $beneficiary) {
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
            'deleted' => $deleted,
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
     * @param  list<int>  $alreadyRemovingIds
     * @return Collection<int, Beneficiary>
     */
    private function duplicateBeneficiariesToDelete(array $alreadyRemovingIds): Collection
    {
        $beneficiaries = Beneficiary::query()
            ->with('medicalRegistration')
            ->where(function ($query): void {
                $query->where(function ($nationalIdQuery): void {
                    $nationalIdQuery->whereNotNull('national_id')
                        ->where('national_id', '!=', '');
                })->orWhere(function ($passportQuery): void {
                    $passportQuery->whereNotNull('passport_number')
                        ->where('passport_number', '!=', '');
                });
            })
            ->when($alreadyRemovingIds !== [], fn ($query) => $query->whereKeyNot($alreadyRemovingIds))
            ->orderBy('id')
            ->get();

        return $beneficiaries
            ->groupBy(fn (Beneficiary $beneficiary): string => $this->identityKey($beneficiary) ?? 'skip:'.$beneficiary->id)
            ->filter(fn (Collection $group, string $key): bool => ! str_starts_with($key, 'skip:') && $group->count() > 1)
            ->flatMap(function (Collection $group): Collection {
                $keep = $this->preferredBeneficiary($group);

                return $group
                    ->reject(fn (Beneficiary $beneficiary): bool => $beneficiary->id === $keep->id)
                    ->values();
            })
            ->values();
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
