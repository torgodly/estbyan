<?php

namespace App\Models;

use App\Enums\CardDeliveryRecipient;
use App\Enums\RegistrationStatus;
use App\Models\Concerns\HasInsuranceCardPrint;
use App\Services\InsuranceCardNumberAssigner;
use App\Support\InsuranceCardNumber;
use App\Support\WorkplaceOptions;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'employee_number',
    'card_number',
    'card_printed_at',
    'cards_delivered_at',
    'cards_delivered_to',
    'cards_delivered_by',
    'national_id',
    'date_of_birth',
    'full_name',
    'workplace',
    'office',
    'is_active',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    use HasInsuranceCardPrint;

    /**
     * @return list<string>
     */
    public static function submittedFormStatuses(): array
    {
        return [
            RegistrationStatus::Submitted->value,
            RegistrationStatus::Editing->value,
            RegistrationStatus::Approved->value,
            RegistrationStatus::Declined->value,
        ];
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'card_printed_at' => 'datetime',
            'cards_delivered_at' => 'datetime',
            'cards_delivered_to' => CardDeliveryRecipient::class,
            'is_active' => 'boolean',
        ];
    }

    public function cardsDeliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cards_delivered_by');
    }

    public function cardsAreDelivered(): bool
    {
        return $this->cards_delivered_at !== null;
    }

    public function markCardsDelivered(User $by, CardDeliveryRecipient $to): void
    {
        $this->forceFill([
            'cards_delivered_at' => now(),
            'cards_delivered_to' => $to,
            'cards_delivered_by' => $by->id,
        ])->save();
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeCardsDelivered(Builder $query): Builder
    {
        return $query->whereNotNull('cards_delivered_at');
    }

    /**
     * Employee card printed and every beneficiary on the latest submitted form is printed too.
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeCardsFullyPrinted(Builder $query): Builder
    {
        return $query
            ->whereNotNull('card_printed_at')
            ->whereDoesntHave(
                'latestSubmittedRegistration.beneficiaries',
                fn (Builder $beneficiaryQuery) => $beneficiaryQuery->whereNull('card_printed_at'),
            );
    }

    public function familyCardsCount(): int
    {
        $beneficiariesCount = $this->latestSubmittedRegistration?->beneficiaries_count
            ?? $this->latestSubmittedRegistration?->beneficiaries()->count()
            ?? 0;

        return 1 + (int) $beneficiariesCount;
    }

    public function medicalRegistrations(): HasMany
    {
        return $this->hasMany(MedicalRegistration::class);
    }

    public function latestMedicalRegistration(): HasOne
    {
        return $this->hasOne(MedicalRegistration::class)->latestOfMany();
    }

    public function latestSubmittedRegistration(): HasOne
    {
        return $this->hasOne(MedicalRegistration::class)
            ->ofMany(['id' => 'max'], function (Builder $query): void {
                $query->whereIn('status', self::submittedFormStatuses());
            });
    }

    public function workplaceLabel(): ?string
    {
        return WorkplaceOptions::labelForKey($this->workplace);
    }

    public function officeLabel(): ?string
    {
        return filled($this->office) ? $this->office : null;
    }

    public function cardNumberLabel(): string
    {
        return InsuranceCardNumber::display($this->card_number);
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeNeedsCardNumberAssignment(Builder $query): Builder
    {
        InsuranceCardNumber::constrainNeedsAssignment($query);

        return $query;
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $employee): void {
            app(InsuranceCardNumberAssigner::class)->fillEmployee($employee);
        });
    }

    public function hasSubmittedForm(): bool
    {
        if (array_key_exists('has_submitted_form', $this->attributes)) {
            return (bool) $this->attributes['has_submitted_form'];
        }

        return $this->medicalRegistrations()
            ->whereIn('status', self::submittedFormStatuses())
            ->exists();
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeSubmittedForm(Builder $query): Builder
    {
        return $query->whereHas(
            'medicalRegistrations',
            fn (Builder $registrationQuery) => $registrationQuery->whereIn('status', self::submittedFormStatuses()),
        );
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeNotSubmittedForm(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'medicalRegistrations',
            fn (Builder $registrationQuery) => $registrationQuery->whereIn('status', self::submittedFormStatuses()),
        );
    }

    public static function findForVerification(string $nationalId): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->where('national_id', $nationalId)
            ->first();
    }
}
