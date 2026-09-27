<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;
use App\Models\RegistrationReviewLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrationReviewService
{
    public function approve(MedicalRegistration $registration, User $reviewer, ?string $note = null): void
    {
        if (! $this->canApprove($registration)) {
            throw new InvalidArgumentException('لا يمكن اعتماد هذا الطلب في حالته الحالية.');
        }

        $trimmedNote = filled($note) ? trim($note) : null;

        DB::transaction(function () use ($registration, $reviewer, $trimmedNote): void {
            $registration->update([
                'status' => RegistrationStatus::Approved,
                'review_note' => $trimmedNote,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
            ]);

            $this->log($registration, $reviewer, RegistrationStatus::Approved, $trimmedNote);
        });
    }

    public function decline(MedicalRegistration $registration, User $reviewer, string $note): void
    {
        $note = trim($note);

        if ($note === '') {
            throw new InvalidArgumentException('سبب الرفض مطلوب.');
        }

        if (! $this->canDecline($registration)) {
            throw new InvalidArgumentException('لا يمكن رفض هذا الطلب في حالته الحالية.');
        }

        DB::transaction(function () use ($registration, $reviewer, $note): void {
            $registration->update([
                'status' => RegistrationStatus::Declined,
                'review_note' => $note,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
            ]);

            $this->log($registration, $reviewer, RegistrationStatus::Declined, $note);
        });
    }

    public function canApprove(MedicalRegistration $registration): bool
    {
        return in_array($registration->status, [
            RegistrationStatus::Submitted,
            RegistrationStatus::Declined,
        ], true);
    }

    public function canDecline(MedicalRegistration $registration): bool
    {
        return in_array($registration->status, [
            RegistrationStatus::Submitted,
            RegistrationStatus::Approved,
        ], true);
    }

    private function log(
        MedicalRegistration $registration,
        User $reviewer,
        RegistrationStatus $action,
        ?string $note,
    ): void {
        RegistrationReviewLog::query()->create([
            'medical_registration_id' => $registration->id,
            'user_id' => $reviewer->id,
            'action' => $action,
            'note' => $note,
        ]);
    }
}
