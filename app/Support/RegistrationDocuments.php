<?php

namespace App\Support;

use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class RegistrationDocuments
{
    public const DISK = 'local';

    public const FAMILY_STATUS = 'family-status';

    public const EMPLOYEE_PHOTO = 'employee-photo';

    public const BENEFICIARY_PHOTO = 'beneficiary-photo';

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    public static function diskName(): string
    {
        return self::DISK;
    }

    public static function pathFor(MedicalRegistration $registration, string $document): ?string
    {
        return match ($document) {
            self::FAMILY_STATUS => $registration->family_status_document_path,
            self::EMPLOYEE_PHOTO => $registration->employee_photo_path,
            default => null,
        };
    }

    public static function url(MedicalRegistration $registration, string $document): ?string
    {
        if (! filled(self::pathFor($registration, $document))) {
            return null;
        }

        return route('registration.documents.show', [
            'registration' => $registration,
            'document' => $document,
        ]);
    }

    public static function beneficiaryUrl(MedicalRegistration $registration, Beneficiary $beneficiary): ?string
    {
        if (! filled($beneficiary->photo_path)) {
            return null;
        }

        return route('registration.documents.beneficiary', [
            'registration' => $registration,
            'beneficiary' => $beneficiary,
        ]);
    }

    public static function mimeType(?string $path): string
    {
        if (! filled($path) || ! self::disk()->exists($path)) {
            return 'application/octet-stream';
        }

        return self::disk()->mimeType($path) ?: 'application/octet-stream';
    }
}
