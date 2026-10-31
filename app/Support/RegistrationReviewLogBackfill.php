<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;
use App\Models\RegistrationReviewLog;

class RegistrationReviewLogBackfill
{
    /**
     * Copy the latest approve/decline stored on each request into the history table.
     */
    public static function run(): int
    {
        $created = 0;

        // Avoid SoftDeletes scopes: this runs during migrate before deleted_at exists.
        MedicalRegistration::withoutGlobalScopes()
            ->whereIn('status', [
                RegistrationStatus::Approved,
                RegistrationStatus::Declined,
            ])
            ->whereNotNull('reviewed_by')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', 'medical_registrations.reviewed_by');
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('registration_review_logs')
                    ->whereColumn(
                        'registration_review_logs.medical_registration_id',
                        'medical_registrations.id',
                    );
            })
            ->orderBy('id')
            ->each(function (MedicalRegistration $registration) use (&$created): void {
                $at = $registration->reviewed_at ?? $registration->updated_at ?? now();

                $log = new RegistrationReviewLog([
                    'medical_registration_id' => $registration->id,
                    'user_id' => $registration->reviewed_by,
                    'action' => $registration->status,
                    'note' => $registration->review_note,
                ]);
                $log->created_at = $at;
                $log->updated_at = $at;
                $log->saveQuietly();

                $created++;
            });

        return $created;
    }
}
