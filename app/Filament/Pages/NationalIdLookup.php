<?php

namespace App\Filament\Pages;

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Read-only lookup by national ID, open to every signed-in user.
 * Card numbers are never sent to the browser.
 */
class NationalIdLookup extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $navigationLabel = 'استعلام';

    protected static ?string $title = 'استعلام';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'lookup';

    protected string $view = 'filament.pages.national-id-lookup';

    protected Width|string|null $maxContentWidth = Width::FourExtraLarge;

    public string $nationalId = '';

    public ?string $searchedNationalId = null;

    public static function canAccess(): bool
    {
        return Auth::user() instanceof User;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'أدخل الرقم الوطني لعرض بيانات الموظف وعائلته. للعرض فقط.';
    }

    public function search(): void
    {
        abort_unless(static::canAccess(), 403);

        $nationalId = static::normalizeNationalId($this->nationalId);
        $this->nationalId = $nationalId;

        $this->searchedNationalId = $nationalId !== '' ? $nationalId : null;
    }

    public function clear(): void
    {
        $this->reset('nationalId', 'searchedNationalId');
    }

    public static function normalizeNationalId(string $value): string
    {
        $value = strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * @return array{
     *     matched_as: string,
     *     matched_name: string,
     *     employee: array<string, string>,
     *     registration: array<string, string>|null,
     *     cards: array{printed: bool, delivered: bool, delivery_label: string|null},
     *     family: list<array{name: string, relationship: string, identity: string, date_of_birth: string, printed: bool, matched: bool}>
     * }|null
     */
    public function result(): ?array
    {
        if ($this->searchedNationalId === null) {
            return null;
        }

        $matchedBeneficiaryId = null;
        $employee = $this->findEmployee($this->searchedNationalId);

        if ($employee === null) {
            $beneficiary = Beneficiary::query()
                ->where('national_id', $this->searchedNationalId)
                ->whereHas('medicalRegistration.employee')
                ->with('medicalRegistration.employee')
                ->latest('id')
                ->first();

            if ($beneficiary === null) {
                return null;
            }

            $matchedBeneficiaryId = $beneficiary->id;
            $employee = $beneficiary->medicalRegistration->employee;
        }

        $registration = $employee->latestSubmittedRegistration ?? $employee->latestMedicalRegistration;

        if ($matchedBeneficiaryId !== null && $registration?->beneficiaries()->whereKey($matchedBeneficiaryId)->doesntExist()) {
            $registration = $beneficiary->medicalRegistration;
        }

        $family = $registration?->beneficiaries()->orderBy('id')->get() ?? collect();
        $matchedBeneficiary = $family->firstWhere('id', $matchedBeneficiaryId);

        return [
            'matched_as' => $matchedBeneficiary !== null ? 'فرد من عائلة الموظف' : 'موظف',
            'matched_name' => $matchedBeneficiary?->full_name ?? $employee->full_name,
            'employee' => [
                'الاسم' => $employee->full_name ?: '—',
                'الرقم الوظيفي' => $employee->employee_number ?: '—',
                'الرقم الوطني' => $employee->national_id ?: '—',
                'تاريخ الميلاد' => $employee->date_of_birth?->format('Y-m-d') ?? '—',
                'الإدارة' => $employee->workplaceLabel() ?? '—',
                'المكتب' => $employee->officeLabel() ?? '—',
            ],
            'registration' => $registration === null ? null : $this->registrationDetails($registration),
            'cards' => [
                'printed' => $employee->card_printed_at !== null,
                'delivered' => $employee->cardsAreDelivered(),
                'delivery_label' => $employee->cardsAreDelivered()
                    ? trim(implode(' · ', array_filter([
                        $employee->cards_delivered_to?->getLabel() ? 'إلى '.$employee->cards_delivered_to->getLabel() : null,
                        $employee->cards_delivered_at?->format('Y-m-d'),
                    ])))
                    : null,
            ],
            'family' => $family
                ->map(fn (Beneficiary $member): array => [
                    'name' => $member->full_name ?: '—',
                    'relationship' => $member->relationship?->label($registration->gender) ?? '—',
                    'identity' => $member->identityLabel(),
                    'date_of_birth' => $member->date_of_birth?->format('Y-m-d') ?? '—',
                    'printed' => $member->card_printed_at !== null,
                    'matched' => $member->id === $matchedBeneficiaryId,
                ])
                ->values()
                ->all(),
        ];
    }

    protected function findEmployee(string $nationalId): ?Employee
    {
        return Employee::query()
            ->where('national_id', $nationalId)
            ->orderByDesc('is_active')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function registrationDetails(MedicalRegistration $registration): array
    {
        return [
            'رقم الطلب' => $registration->reference_number ?: '—',
            'حالة الطلب' => $registration->status?->label() ?? '—',
            'الوظيفة' => $registration->jobTitleLabel() ?? '—',
            'الحالة الاجتماعية' => $registration->marital_status?->label() ?? '—',
            'المدينة' => $registration->cityLabel() ?? '—',
            'تاريخ الإرسال' => $registration->submitted_at?->format('Y-m-d') ?? '—',
        ];
    }
}
