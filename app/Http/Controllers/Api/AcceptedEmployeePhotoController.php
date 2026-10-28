<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Support\RegistrationDocuments;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcceptedEmployeePhotoController extends Controller
{
    public function employee(MedicalRegistration $registration): StreamedResponse
    {
        $this->ensureApproved($registration);

        return $this->file($registration->employee_photo_path);
    }

    public function familyMember(MedicalRegistration $registration, Beneficiary $beneficiary): StreamedResponse
    {
        $this->ensureApproved($registration);

        abort_unless($beneficiary->medical_registration_id === $registration->id, 404);

        return $this->file($beneficiary->photo_path);
    }

    private function ensureApproved(MedicalRegistration $registration): void
    {
        $registration->loadMissing('employee');

        abort_unless(
            $registration->isApproved() && $registration->employee?->cardsAreDelivered() === true,
            404,
        );
    }

    private function file(?string $path): StreamedResponse
    {
        abort_unless(filled($path) && RegistrationDocuments::disk()->exists($path), 404);

        return RegistrationDocuments::disk()->response($path, headers: [
            'Content-Type' => $this->mimeType($path),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'heic' => 'image/heic',
            default => 'application/octet-stream',
        };
    }
}
