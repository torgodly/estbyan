<?php

namespace App\Models;

use App\Enums\BeneficiaryRelationship;
use App\Enums\BloodType;
use Database\Factories\BeneficiaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'medical_registration_id',
    'full_name',
    'relationship',
    'is_libyan',
    'nationality',
    'national_id',
    'passport_number',
    'date_of_birth',
    'blood_type',
    'has_chronic_condition',
    'has_chronic_conditions',
    'chronic_conditions',
    'has_tumor',
    'has_surgery_history',
    'uses_medical_devices',
    'hospitalized_recently',
    'traveled_for_treatment',
    'photo_path',
])]
class Beneficiary extends Model
{
    /** @use HasFactory<BeneficiaryFactory> */
    use HasFactory;

    protected $attributes = [
        'is_libyan' => true,
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'relationship' => BeneficiaryRelationship::class,
            'blood_type' => BloodType::class,
            'is_libyan' => 'boolean',
            'has_chronic_condition' => 'boolean',
            'has_chronic_conditions' => 'boolean',
            'chronic_conditions' => 'array',
            'has_tumor' => 'boolean',
            'has_surgery_history' => 'boolean',
            'uses_medical_devices' => 'boolean',
            'hospitalized_recently' => 'boolean',
            'traveled_for_treatment' => 'boolean',
        ];
    }

    public function medicalRegistration(): BelongsTo
    {
        return $this->belongsTo(MedicalRegistration::class);
    }

    public function nationalityLabel(): ?string
    {
        if ($this->is_libyan) {
            return 'ليبيا';
        }

        if (! filled($this->nationality)) {
            return null;
        }

        return config('registration.nationalities.'.$this->nationality) ?? $this->nationality;
    }

    public function identityLabel(): string
    {
        if ($this->is_libyan) {
            return filled($this->national_id) ? (string) $this->national_id : '—';
        }

        $parts = array_filter([
            $this->nationalityLabel(),
            filled($this->passport_number) ? 'جواز: '.$this->passport_number : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    protected static function booted(): void
    {
        static::saving(function (Beneficiary $beneficiary): void {
            $beneficiary->has_chronic_condition = (bool) $beneficiary->has_chronic_conditions;
        });
    }
}
