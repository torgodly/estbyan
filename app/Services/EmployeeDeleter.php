<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;
use App\Support\RegistrationDocuments;
use Illuminate\Support\Facades\DB;

/**
 * Permanently removes an employee with everything attached: all their registrations
 * (including soft-deleted ones), every family member (including cancelled and soft-deleted),
 * review logs, review flags and the uploaded photos and documents.
 */
class EmployeeDeleter
{
    /**
     * @return array{registrations: int, family_members: int, files: int}
     */
    public function delete(Employee $employee): array
    {
        $registrations = MedicalRegistration::withTrashed()
            ->where('employee_id', $employee->id)
            ->get();

        $familyMembers = Beneficiary::queryWithCancelled()
            ->withTrashed()
            ->whereIn('medical_registration_id', $registrations->modelKeys())
            ->get();

        $files = array_values(array_unique(array_filter([
            ...$registrations->pluck('family_status_document_path')->all(),
            ...$registrations->pluck('employee_photo_path')->all(),
            ...$familyMembers->pluck('photo_path')->all(),
        ])));

        DB::transaction(function () use ($employee, $registrations, $familyMembers): void {
            ReviewFlag::query()
                ->where(fn ($query) => $query
                    ->where('flaggable_type', $employee->getMorphClass())
                    ->where('flaggable_id', $employee->id))
                ->orWhere(fn ($query) => $query
                    ->where('flaggable_type', (new Beneficiary)->getMorphClass())
                    ->whereIn('flaggable_id', $familyMembers->modelKeys() ?: [0]))
                ->delete();

            $familyMembers->each->forceDelete();
            $registrations->each->forceDelete();

            Employee::withTrashed()->whereKey($employee->id)->firstOrFail()->forceDelete();
        });

        $disk = RegistrationDocuments::disk();
        $deletedFiles = 0;

        foreach ($files as $path) {
            if ($disk->exists($path) && $disk->delete($path)) {
                $deletedFiles++;
            }
        }

        return [
            'registrations' => $registrations->count(),
            'family_members' => $familyMembers->count(),
            'files' => $deletedFiles,
        ];
    }
}
