<?php

namespace App\Models;

use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\RegistrationStatus;
use App\Support\InsuranceCardNumber;
use App\Support\WorkplaceOptions;
use Database\Factories\MedicalRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'reference_number',
    'employee_id',
    'status',
    'review_note',
    'reviewed_at',
    'reviewed_by',
    'current_step',
    'employee_number',
    'national_id',
    'date_of_birth',
    'blood_type',
    'full_name',
    'consent_at',
    'workplace',
    'job_title',
    'gender',
    'marital_status',
    'beneficiaries_count',
    'phone',
    'whatsapp',
    'email',
    'city',
    'address',
    'has_chronic_conditions',
    'chronic_conditions',
    'has_tumor',
    'has_surgery_history',
    'uses_medical_devices',
    'hospitalized_recently',
    'traveled_for_treatment',
    'family_status_document_path',
    'employee_photo_path',
    'submitted_at',
])]
class MedicalRegistration extends Model
{
    /** @use HasFactory<MedicalRegistrationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'blood_type' => BloodType::class,
            'consent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'status' => RegistrationStatus::class,
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'has_chronic_conditions' => 'boolean',
            'chronic_conditions' => 'array',
            'has_tumor' => 'boolean',
            'has_surgery_history' => 'boolean',
            'uses_medical_devices' => 'boolean',
            'hospitalized_recently' => 'boolean',
            'traveled_for_treatment' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MedicalRegistration $registration): void {
            if (empty($registration->uuid)) {
                $registration->uuid = (string) Str::uuid();
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewLogs(): HasMany
    {
        return $this->hasMany(RegistrationReviewLog::class)->latest('id');
    }

    /**
     * Stored history, or the latest approve/decline on the request when logs were never written.
     *
     * @return Collection<int, RegistrationReviewLog>
     */
    public function reviewHistory(): Collection
    {
        $logs = $this->relationLoaded('reviewLogs')
            ? $this->reviewLogs
            : $this->reviewLogs()->with('user')->get();

        if ($logs->isNotEmpty()) {
            return $logs;
        }

        if (! $this->status instanceof RegistrationStatus
            || ! in_array($this->status, [RegistrationStatus::Approved, RegistrationStatus::Declined], true)
            || $this->reviewed_at === null) {
            return $logs;
        }

        $fallback = new RegistrationReviewLog([
            'medical_registration_id' => $this->id,
            'user_id' => $this->reviewed_by,
            'action' => $this->status,
            'note' => $this->review_note,
        ]);
        $fallback->created_at = $this->reviewed_at;
        $fallback->setRelation('user', $this->reviewer);

        return collect([$fallback]);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public function scopeMatchingSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search): void {
            $query
                ->where(fn (Builder $query): Builder => $query->matchingName($search))
                ->orWhere(fn (Builder $query): Builder => $query->matchingNationalId($search))
                ->orWhere(fn (Builder $query): Builder => $query->matchingPhone($search))
                ->orWhere(fn (Builder $query): Builder => $query->matchingCardNumber($search))
                ->orWhere(fn (Builder $query): Builder => $query->matchingReferenceOrEmployeeNumber($search));
        });
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public function scopeMatchingNationalId(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $search) ?? '';
        $beneficiary = new Beneficiary;

        return $query->where(function (Builder $query) use ($search, $digits, $beneficiary): void {
            $nationalId = $query->qualifyColumn('national_id');

            $query->where($nationalId, 'like', '%'.$search.'%')
                ->orWhereIn(
                    $query->qualifyColumn('id'),
                    $beneficiary->newQuery()
                        ->where(function (Builder $query) use ($search, $digits, $beneficiary): void {
                            $query->where($beneficiary->qualifyColumn('national_id'), 'like', '%'.$search.'%')
                                ->orWhere($beneficiary->qualifyColumn('passport_number'), 'like', '%'.$search.'%');

                            if ($digits !== '' && $digits !== $search) {
                                $query->orWhere($beneficiary->qualifyColumn('national_id'), 'like', '%'.$digits.'%');
                            }
                        })
                        ->select($beneficiary->qualifyColumn('medical_registration_id')),
                );

            if ($digits !== '' && $digits !== $search) {
                $query->orWhere($nationalId, 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public function scopeMatchingName(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $beneficiary = new Beneficiary;
        $employee = new Employee;

        return $query->where(function (Builder $query) use ($search, $beneficiary, $employee): void {
            $query
                ->where($query->qualifyColumn('full_name'), 'like', '%'.$search.'%')
                ->orWhereIn(
                    $query->qualifyColumn('id'),
                    $beneficiary->newQuery()
                        ->where($beneficiary->qualifyColumn('full_name'), 'like', '%'.$search.'%')
                        ->select($beneficiary->qualifyColumn('medical_registration_id')),
                )
                ->orWhereIn(
                    $query->qualifyColumn('employee_id'),
                    $employee->newQuery()
                        ->where($employee->qualifyColumn('full_name'), 'like', '%'.$search.'%')
                        ->select($employee->qualifyColumn('id')),
                );
        });
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public function scopeMatchingPhone(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $search) ?? '';

        return $query->where(function (Builder $query) use ($search, $digits): void {
            $phone = $query->qualifyColumn('phone');
            $whatsapp = $query->qualifyColumn('whatsapp');

            $query->where($phone, 'like', '%'.$search.'%')
                ->orWhere($whatsapp, 'like', '%'.$search.'%');

            if ($digits !== '' && $digits !== $search) {
                $query->orWhere($phone, 'like', '%'.$digits.'%')
                    ->orWhere($whatsapp, 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public function scopeMatchingCardNumber(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $search) ?? '';
        $normalized = InsuranceCardNumber::normalize($search);
        $terms = array_values(array_unique(array_filter([
            $search,
            $normalized,
            $digits !== '' ? $digits : null,
        ], fn (?string $term): bool => filled($term))));

        $beneficiary = new Beneficiary;
        $employee = new Employee;

        return $query->where(function (Builder $query) use ($terms, $beneficiary, $employee): void {
            foreach ($terms as $term) {
                $query
                    ->orWhereIn(
                        $query->qualifyColumn('employee_id'),
                        $employee->newQuery()
                            ->where($employee->qualifyColumn('card_number'), 'like', '%'.$term.'%')
                            ->select($employee->qualifyColumn('id')),
                    )
                    ->orWhereIn(
                        $query->qualifyColumn('id'),
                        $beneficiary->newQuery()
                            ->where($beneficiary->qualifyColumn('card_number'), 'like', '%'.$term.'%')
                            ->select($beneficiary->qualifyColumn('medical_registration_id')),
                    );
            }
        });
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public function scopeMatchingReferenceOrEmployeeNumber(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $search) ?? '';
        $employee = new Employee;

        return $query->where(function (Builder $query) use ($search, $digits, $employee): void {
            $reference = $query->qualifyColumn('reference_number');
            $employeeNumber = $query->qualifyColumn('employee_number');
            $email = $query->qualifyColumn('email');

            $query->where($reference, 'like', '%'.$search.'%')
                ->orWhere($employeeNumber, 'like', '%'.$search.'%')
                ->orWhere($email, 'like', '%'.$search.'%')
                ->orWhereIn(
                    $query->qualifyColumn('employee_id'),
                    $employee->newQuery()
                        ->where($employee->qualifyColumn('employee_number'), 'like', '%'.$search.'%')
                        ->select($employee->qualifyColumn('id')),
                );

            if ($digits !== '' && $digits !== $search) {
                $query->orWhere($reference, 'like', '%'.$digits.'%')
                    ->orWhere($employeeNumber, 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public function scopeCardsFullyPrinted(Builder $query): Builder
    {
        return $query
            ->whereHas('employee', fn (Builder $query) => $query->whereNotNull('card_printed_at'))
            ->whereDoesntHave('beneficiaries', fn (Builder $query) => $query->whereNull('card_printed_at'));
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public function scopeCardsUnprinted(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->whereHas('employee', fn (Builder $query) => $query->whereNull('card_printed_at'))
                    ->orWhereDoesntHave('employee');
            })
            ->whereDoesntHave('beneficiaries', fn (Builder $query) => $query->whereNotNull('card_printed_at'));
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public function scopeCardsPartiallyPrinted(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->whereHas('employee', fn (Builder $query) => $query->whereNotNull('card_printed_at'))
                    ->orWhereHas('beneficiaries', fn (Builder $query) => $query->whereNotNull('card_printed_at'));
            })
            ->where(function (Builder $query): void {
                $query->whereHas('employee', fn (Builder $query) => $query->whereNull('card_printed_at'))
                    ->orWhereDoesntHave('employee')
                    ->orWhereHas('beneficiaries', fn (Builder $query) => $query->whereNull('card_printed_at'));
            });
    }

    public function isSubmitted(): bool
    {
        return $this->status === RegistrationStatus::Submitted;
    }

    public function isApproved(): bool
    {
        return $this->status === RegistrationStatus::Approved;
    }

    public function isDeclined(): bool
    {
        return $this->status === RegistrationStatus::Declined;
    }

    public function isEditing(): bool
    {
        return $this->status === RegistrationStatus::Editing;
    }

    public function isPendingReview(): bool
    {
        return $this->status === RegistrationStatus::Submitted;
    }

    public function wasResubmittedAfterDecline(): bool
    {
        if (! $this->isPendingReview() || $this->submitted_at === null) {
            return false;
        }

        return $this->reviewHistory()->contains(
            fn (RegistrationReviewLog $log): bool => $log->action === RegistrationStatus::Declined,
        );
    }

    public function hasDocuments(): bool
    {
        return filled($this->family_status_document_path) && filled($this->employee_photo_path);
    }

    public function isEditableByEmployee(): bool
    {
        return $this->status->isEditableByEmployee();
    }

    public static function generateReferenceNumber(): string
    {
        $prefix = 'SC'.now()->format('y');

        $lastReference = static::query()
            ->whereNotNull('reference_number')
            ->where('reference_number', 'like', $prefix.'-%')
            ->lockForUpdate()
            ->orderByDesc('reference_number')
            ->value('reference_number');

        $sequence = 1;

        if ($lastReference !== null && preg_match('/-(\d+)$/', $lastReference, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('%s-%05d', $prefix, $sequence);
    }

    public function workplaceLabel(): ?string
    {
        return WorkplaceOptions::labelForKey($this->workplace);
    }

    public function officeLabel(): ?string
    {
        return $this->employee?->officeLabel();
    }

    public function jobTitleLabel(): ?string
    {
        return $this->job_title
            ? (config('registration.job_titles')[$this->job_title] ?? $this->job_title)
            : null;
    }

    public function cityLabel(): ?string
    {
        return $this->city
            ? (config('registration.cities')[$this->city] ?? $this->city)
            : null;
    }
}
