<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\MedicalRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReviewerQueueSplitter
{
    /**
     * Current reviewer accounts, in a stable order so the split does not jump around.
     *
     * @return Collection<int, User>
     */
    public static function reviewers(): Collection
    {
        return User::query()
            ->where('role', UserRole::Reviewer)
            ->orderBy('email')
            ->orderBy('id')
            ->get()
            ->values();
    }

    public static function constrain(Builder $query, User $reviewer): Builder
    {
        $slot = self::slotFor($reviewer);

        if ($slot === null) {
            return $query->whereRaw('0 = 1');
        }

        [$index, $count] = $slot;

        return $query->whereRaw(
            $query->getModel()->qualifyColumn('id').' % ? = ?',
            [$count, $index],
        );
    }

    public static function owns(User $reviewer, MedicalRegistration $registration): bool
    {
        $slot = self::slotFor($reviewer);

        if ($slot === null) {
            return false;
        }

        [$index, $count] = $slot;

        return ((int) $registration->getKey() % $count) === $index;
    }

    /**
     * @return array{int, int}|null
     */
    private static function slotFor(User $reviewer): ?array
    {
        $fixedEmails = ReviewerAccounts::emails();
        $fixedIndex = array_search($reviewer->email, $fixedEmails, true);

        if ($fixedIndex !== false) {
            return [(int) $fixedIndex, count($fixedEmails)];
        }

        $reviewers = self::reviewers();
        $index = $reviewers->search(fn (User $user): bool => $user->is($reviewer));

        if ($index === false || $reviewers->isEmpty()) {
            return null;
        }

        return [(int) $index, $reviewers->count()];
    }
}
