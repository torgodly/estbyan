<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;
use App\Models\User;
use InvalidArgumentException;

class RegistrationReviewService
{
    public function approve(MedicalRegistration $registration, User $reviewer, ?string $note = null): void
    {
        if (! $this->canApprove($registration)) {
            throw new InvalidArgumentException('لا يمكن اعتماد هذا الطلب في حالته الحالية.');
        }

        $registration->update([
            'status' => RegistrationStatus::Approved,
            'review_note' => filled($note) ? trim($note) : null,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);
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

        $registration->update([
            'status' => RegistrationStatus::Declined,
            'review_note' => $note,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);
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
}
