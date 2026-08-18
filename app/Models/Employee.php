<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Support\WorkplaceOptions;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'employee_number',
    'national_id',
    'date_of_birth',
    'full_name',
    'workplace',
    'is_active',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

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
            'is_active' => 'boolean',
        ];
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
