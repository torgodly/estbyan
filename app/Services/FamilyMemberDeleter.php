<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;
use App\Support\RegistrationDocuments;
use Illuminate\Support\Facades\DB;

/**
 * Permanently removes one family member (including a cancelled or soft-deleted one) with their
 * review flags and uploaded photo, and refreshes the registration's family member count.
 */
class FamilyMemberDeleter
{
    /**
     * @return array{name: string, photo_deleted: bool}
     */
    public function delete(int $beneficiaryId): array
    {
        $familyMember = Beneficiary::queryWithCancelled()
            ->withTrashed()
            ->findOrFail($beneficiaryId);

        $name = (string) $familyMember->full_name;
        $photoPath = $familyMember->photo_path;
        $registrationId = $familyMember->medical_registration_id;

        DB::transaction(function () use ($familyMember, $registrationId): void {
            ReviewFlag::withTrashed()
                ->where('flaggable_type', $familyMember->getMorphClass())
                ->where('flaggable_id', $familyMember->id)
                ->forceDelete();

            $familyMember->forceDelete();

            $registration = MedicalRegistration::withTrashed()->find($registrationId);

            $registration?->forceFill([
                'beneficiaries_count' => $registration->beneficiaries()->count(),
            ])->saveQuietly();
        });

        $photoDeleted = false;

        if (filled($photoPath) && ! $this->photoStillUsed($photoPath)) {
            $disk = RegistrationDocuments::disk();
            $photoDeleted = $disk->exists($photoPath) && $disk->delete($photoPath);
        }

        return [
            'name' => $name,
            'photo_deleted' => $photoDeleted,
        ];
    }

    private function photoStillUsed(string $path): bool
    {
        return Beneficiary::queryWithCancelled()->withTrashed()->where('photo_path', $path)->exists()
            || MedicalRegistration::withTrashed()
                ->where(fn ($query) => $query
                    ->where('employee_photo_path', $path)
                    ->orWhere('family_status_document_path', $path))
                ->exists();
    }
}
