<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ReviewerAccounts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('creates the reviewer accounts that can only review pending requests', function () {
    Artisan::call('reviewers:create');
    $output = Artisan::output();

    $users = User::query()->whereIn('email', ReviewerAccounts::emails())->orderBy('email')->get();

    expect($users)->toHaveCount(count(ReviewerAccounts::definitions()));

    foreach (ReviewerAccounts::definitions() as $definition) {
        $user = $users->firstWhere('email', $definition['email']);

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe($definition['name'])
            ->and($user->role)->toBe(UserRole::Reviewer)
            ->and($user->isReviewer())->toBeTrue()
            ->and($user->canAccessFullAdmin())->toBeFalse()
            ->and($user->canManageInsuranceCards())->toBeFalse()
            ->and(Hash::check($definition['password'], $user->password))->toBeTrue()
            ->and($output)->toContain($definition['email'])
            ->and($output)->toContain($definition['password']);
    }
});

it('updates existing reviewer accounts when the command runs again', function () {
    Artisan::call('reviewers:create');

    $first = ReviewerAccounts::definitions()[0];

    User::query()->where('email', $first['email'])->update([
        'name' => 'اسم قديم',
        'role' => UserRole::Hr,
    ]);

    Artisan::call('reviewers:create');

    $user = User::query()->where('email', $first['email'])->first();

    expect(User::query()->whereIn('email', ReviewerAccounts::emails())->count())->toBe(count(ReviewerAccounts::definitions()))
        ->and($user?->name)->toBe($first['name'])
        ->and($user?->role)->toBe(UserRole::Reviewer)
        ->and(Hash::check($first['password'], $user?->password))->toBeTrue();
});
