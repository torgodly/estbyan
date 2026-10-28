<?php

namespace App\Models;

use App\Enums\ReviewFlagReason;
use Database\Factories\ReviewFlagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A person (employee or family member) the system is not sure about and a human must check.
 * One flag per person per reason; raising it again only refreshes the note.
 */
#[Fillable([
    'flaggable_type',
    'flaggable_id',
    'reason',
    'note',
])]
class ReviewFlag extends Model
{
    /** @use HasFactory<ReviewFlagFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reason' => ReviewFlagReason::class,
            'resolved_at' => 'datetime',
        ];
    }

    public static function raise(Employee|Beneficiary $person, ReviewFlagReason $reason, string $note): self
    {
        $flaggable = $person instanceof Beneficiary
            ? Beneficiary::queryWithCancelled()->whereKey($person->getKey())->firstOrFail()
            : $person;

        return static::query()->updateOrCreate(
            [
                'flaggable_type' => $flaggable->getMorphClass(),
                'flaggable_id' => $flaggable->getKey(),
                'reason' => $reason,
            ],
            ['note' => $note],
        );
    }

    public function flaggable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScope(Beneficiary::ACTIVE_SCOPE);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * A null user means the system closed it (e.g. a data decision command).
     */
    public function resolve(?User $by, ?string $note = null): void
    {
        $this->forceFill([
            'resolved_at' => now(),
            'resolved_by' => $by?->id,
            'resolution_note' => $note,
        ])->save();
    }

    /**
     * @param  Builder<ReviewFlag>  $query
     * @return Builder<ReviewFlag>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function personName(): string
    {
        return $this->flaggable?->full_name ?: '—';
    }

    public function employee(): ?Employee
    {
        $person = $this->flaggable;

        return $person instanceof Beneficiary
            ? $person->medicalRegistration?->employee
            : $person;
    }

    public function registration(): ?MedicalRegistration
    {
        $person = $this->flaggable;

        if ($person instanceof Beneficiary) {
            return $person->medicalRegistration;
        }

        if ($person instanceof Employee) {
            return $person->latestSubmittedRegistration ?? $person->latestMedicalRegistration;
        }

        return null;
    }
}
