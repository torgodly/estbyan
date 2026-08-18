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

    public static function maxKilobytes(): int
    {
        return (int) config('registration.uploads.max_kilobytes', 51200);
    }

    public static function maxMegabytes(): int
    {
        return (int) ceil(self::maxKilobytes() / 1024);
    }

    /**
     * @return list<string>
     */
    public static function familyMimes(): array
    {
        return config('registration.uploads.family_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif']);
    }

    /**
     * @return list<string>
     */
    public static function photoMimes(): array
    {
        return config('registration.uploads.photo_mimes', ['jpg', 'jpeg', 'png', 'webp']);
    }

    /**
     * @return list<string>
     */
    public static function familyValidationRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', self::familyMimes()),
            'max:'.self::maxKilobytes(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function photoValidationRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', self::photoMimes()),
            'max:'.self::maxKilobytes(),
        ];
    }

    public static function isImagePath(?string $path): bool
    {
        return filled($path) && (bool) preg_match('/\.(jpe?g|png|webp|gif|heic|heif)$/i', $path);
    }

    public static function familyAcceptAttribute(): string
    {
        return 'application/pdf,image/jpeg,image/png,image/webp,image/heic,image/heif,.pdf,.jpg,.jpeg,.png,.webp,.heic,.heif';
    }

    public static function photoAcceptAttribute(): string
    {
        return 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp';
    }
}
