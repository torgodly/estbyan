<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Database\Factories\RegistrationReviewLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'medical_registration_id',
    'user_id',
    'action',
    'note',
])]
class RegistrationReviewLog extends Model
{
    /** @use HasFactory<RegistrationReviewLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'action' => RegistrationStatus::class,
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(MedicalRegistration::class, 'medical_registration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
