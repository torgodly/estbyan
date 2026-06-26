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
    'national_id',
    'date_of_birth',
    'blood_type',
    'has_chronic_condition',
    'photo_path',
])]
class Beneficiary extends Model
{
    /** @use HasFactory<BeneficiaryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'relationship' => BeneficiaryRelationship::class,
            'blood_type' => BloodType::class,
            'has_chronic_condition' => 'boolean',
        ];
    }

    public function medicalRegistration(): BelongsTo
    {
        return $this->belongsTo(MedicalRegistration::class);
    }
}
