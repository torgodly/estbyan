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

        MedicalRegistration::query()
            ->whereIn('status', [
                RegistrationStatus::Approved,
                RegistrationStatus::Declined,
            ])
            ->whereNotNull('reviewed_by')
            ->whereHas('reviewer')
            ->whereDoesntHave('reviewLogs')
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
                $log->save();

                $created++;
            });

        return $created;
    }
}
