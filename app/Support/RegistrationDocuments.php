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

    public static function photoMaxKilobytes(): int
    {
        return (int) config('registration.uploads.photo_max_kilobytes', 10240);
    }

    public static function photoMaxMegabytes(): int
    {
        return (int) ceil(self::photoMaxKilobytes() / 1024);
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
        return config('registration.uploads.photo_mimes', ['jpg', 'jpeg', 'png']);
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
            'max:'.self::photoMaxKilobytes(),
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
        return 'image/jpeg,image/png,.jpg,.jpeg,.png';
    }

    public static function photoSizeHint(): string
    {
        return 'JPG أو PNG — الحد الأقصى '.self::photoMaxMegabytes().' ميجابايت';
    }

    public static function requirementsTitle(): string
    {
        return 'تعليمات ومتطلبات الصورة الشخصية';
    }

    public static function requirementsIntro(): string
    {
        return 'يرجى الالتزام بالمتطلبات التالية لضمان قبول الصورة:';
    }

    public static function requirementsNote(): string
    {
        return 'الصور التي لا تستوفي هذه المتطلبات سيتم رفضها وطلب إعادة رفعها.';
    }

    public static function childrenRequirementsTitle(): string
    {
        return 'تعليمات خاصة بالأطفال والرضع';
    }

    /**
     * @return list<string>
     */
    public static function childrenRequirementItems(): array
    {
        return [
            'الظهور: يجب أن يظهر الطفل بمفرده في الصورة (دون ظهور يدي المُمسك به أو ظهر الكرسي).',
            'الرضع (دون سن العامين): تُقبل بعض المرونة البسيطة في إغلاق العينين أو فتح الفم قليلاً، مع الالتزام التام بشرط الخلفية البيضاء ووضوح الوجه.',
        ];
    }

    public static function formatRequirement(): string
    {
        return 'يجب أن تكون الصورة JPG أو JPEG أو PNG، وحجمها لا يتجاوز '.self::photoMaxMegabytes().' ميجابايت.';
    }

    /**
     * @return list<string>
     */
    public static function requirementItems(): array
    {
        return [
            'صورة شخصية حديثة وواضحة، ويفضل ألا يتجاوز تاريخ التقاطها 6 أشهر.',
            'خلفية بيضاء سادة فقط، خالية من النقوش والظلال.',
            'مواجهة الكاميرا بشكل مباشر، مع إبقاء الرأس مستقيماً وتعبير وجه محايد.',
            'يجب أن يكون الوجه بالكامل والعينان واضحتين، من أعلى الجبهة إلى أسفل الذقن.',
            'حجم الوجه في الصورة: يجب أن يشغل الوجه ما بين 70% إلى 80% من المساحة الكلية.',
            'إضاءة متوازنة دون ظلال قوية أو انعكاسات على الوجه.',
            'يمنع استخدام صور السيلفي، الصور الجماعية، الصور غير الرسمية، أو الصور المأخوذة من مناسبات أو رحلات.',
            'يمنع استخدام الفلاتر أو تعديلات التجميل أو معالجة الصورة بشكل مبالغ فيه.',
            'النظارات الشمسية والعدسات الملونة غير مسموح بها. ويُسمح بالنظارات الطبية بشرط وضوح العينين وعدم وجود انعكاسات.',
            'يجب أن تكون جودة الصورة عالية وغير ضبابية.',
        ];
    }
}
