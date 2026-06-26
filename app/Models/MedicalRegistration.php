<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\RegistrationStatus;
use Database\Factories\MedicalRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'reference_number',
    'employee_id',
    'status',
    'current_step',
    'employee_number',
    'national_id',
    'date_of_birth',
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
            'consent_at' => 'datetime',
            'submitted_at' => 'datetime',
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

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function isSubmitted(): bool
    {
        return $this->status === RegistrationStatus::Submitted;
    }

    public static function generateReferenceNumber(): string
    {
        $prefix = 'SC'.now()->format('y');

        $lastReference = static::query()
            ->whereNotNull('reference_number')
            ->where('reference_number', 'like', $prefix.'-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('reference_number');

        $sequence = 1;

        if ($lastReference !== null && preg_match('/-(\d+)$/', $lastReference, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('%s-%05d', $prefix, $sequence);
    }

    public function workplaceLabel(): ?string
    {
        return $this->workplace
            ? (config('registration.workplaces')[$this->workplace] ?? $this->workplace)
            : null;
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
