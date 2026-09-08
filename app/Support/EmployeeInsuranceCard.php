<?php

namespace App\Support;

use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use DateTimeInterface;
use Illuminate\Support\Collection;
use RuntimeException;

final readonly class EmployeeInsuranceCard
{
    public function __construct(
        public string $name,
        public string $reference,
        public string $dateOfBirth,
        public string $issuedAt,
        public string $jobTitle,
        public string $bloodType,
        public string $kind,
        public ?string $barcodeSvg,
        public ?string $photoDataUri,
        public ?string $photoUrl,
        public string $frontArtworkUrl,
        public string $backArtworkUrl,
        public string $fontDataUri,
        public string $fontUrl,
    ) {}

    /**
     * @return Collection<int, self>
     */
    public static function collection(MedicalRegistration $registration): Collection
    {
        $registration->loadMissing(['beneficiaries', 'employee']);

        return collect([self::from($registration)])
            ->concat($registration->beneficiaries->map(
                fn (Beneficiary $beneficiary): self => self::fromBeneficiary($registration, $beneficiary),
            ))
            ->values();
    }

    public static function from(MedicalRegistration $registration): self
    {
        $registration->loadMissing('employee');
        $cardNumber = $registration->employee?->card_number;

        return new self(
            name: $registration->full_name ?: '—',
            reference: InsuranceCardNumber::display($cardNumber),
            dateOfBirth: self::formatCardDate($registration->date_of_birth),
            issuedAt: self::formatCardDate(self::issuedAtFor($registration)),
            jobTitle: $registration->jobTitleLabel() ?: 'موظف',
            bloodType: '—',
            kind: 'employee',
            barcodeSvg: InsuranceCardNumber::isValid($cardNumber)
                ? Code128Barcode::svg($cardNumber)
                : null,
            photoDataUri: self::photoDataUriFromPath($registration->employee_photo_path),
            photoUrl: RegistrationDocuments::url($registration, RegistrationDocuments::EMPLOYEE_PHOTO),
            frontArtworkUrl: asset('cards/card-front.png'),
            backArtworkUrl: asset('cards/card-back.png'),
            fontDataUri: self::fontDataUri(),
            fontUrl: asset('fonts/SomarSans-SemiBold.ttf'),
        );
    }

    public static function fromBeneficiary(MedicalRegistration $registration, Beneficiary $beneficiary): self
    {
        $cardNumber = $beneficiary->card_number;

        return new self(
            name: $beneficiary->full_name ?: '—',
            reference: InsuranceCardNumber::display($cardNumber),
            dateOfBirth: self::formatCardDate($beneficiary->date_of_birth),
            issuedAt: self::formatCardDate(self::issuedAtFor($registration)),
            jobTitle: $beneficiary->relationship?->label($registration->gender) ?: 'مستفيد',
            bloodType: $beneficiary->blood_type?->cardLabel() ?? '—',
            kind: 'beneficiary',
            barcodeSvg: InsuranceCardNumber::isValid($cardNumber)
                ? Code128Barcode::svg($cardNumber)
                : null,
            photoDataUri: self::photoDataUriFromPath($beneficiary->photo_path),
            photoUrl: RegistrationDocuments::beneficiaryUrl($registration, $beneficiary),
            frontArtworkUrl: asset('cards/card-front.png'),
            backArtworkUrl: asset('cards/card-back.png'),
            fontDataUri: self::fontDataUri(),
            fontUrl: asset('fonts/SomarSans-SemiBold.ttf'),
        );
    }

    public static function issuedAtFor(MedicalRegistration $registration): DateTimeInterface
    {
        return $registration->reviewed_at
            ?? $registration->submitted_at
            ?? $registration->created_at
            ?? now();
    }

    public function filename(): string
    {
        $cardNumber = InsuranceCardNumber::normalize($this->reference);

        return 'employee-card-'.($cardNumber ?? 'draft');
    }

    public static function packFilename(MedicalRegistration $registration): string
    {
        $reference = filled($registration->reference_number)
            ? $registration->reference_number
            : 'draft';

        return 'insurance-cards-'.$reference;
    }

    public function heading(): string
    {
        return $this->kind === 'employee'
            ? 'بطاقة الموظف'
            : 'بطاقة المستفيد';
    }

    public function photoSrc(bool $embedAssets): ?string
    {
        if ($embedAssets) {
            return $this->photoDataUri ?: $this->photoUrl;
        }

        return $this->photoUrl ?: $this->photoDataUri;
    }

    public static function formatCardDate(?DateTimeInterface $date): string
    {
        return $date?->format('Y / m / d') ?? '—';
    }

    public static function fontDataUri(): string
    {
        return once(function (): string {
            $path = resource_path('fonts/SomarSans-SemiBold.ttf');

            if (! is_readable($path)) {
                throw new RuntimeException('Missing font file: SomarSans-SemiBold.ttf');
            }

            return 'data:font/truetype;base64,'.base64_encode((string) file_get_contents($path));
        });
    }

    public static function photoDataUri(MedicalRegistration $registration): ?string
    {
        return self::photoDataUriFromPath($registration->employee_photo_path);
    }

    public static function photoDataUriFromPath(?string $path): ?string
    {
        if (! filled($path) || ! RegistrationDocuments::disk()->exists($path)) {
            return null;
        }

        $contents = RegistrationDocuments::disk()->get($path);

        if (! filled($contents)) {
            return null;
        }

        return 'data:'.RegistrationDocuments::mimeType($path).';base64,'.base64_encode($contents);
    }
}
