<?php

use App\Enums\UserRole;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\ReviewerAccounts;
use App\Support\ReviewerQueueSplitter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('creates the three extra reviewer accounts and resplits pending reviews', function () {
    foreach (array_slice(ReviewerAccounts::definitions(), 0, 4) as $definition) {
        User::factory()->reviewer()->create([
            'name' => $definition['name'],
            'email' => $definition['email'],
            'password' => $definition['password'],
        ]);
    }

    $pending = MedicalRegistration::factory()->submitted()->count(14)->create();

    Artisan::call('reviewers:expand');
    $output = Artisan::output();

    $users = User::query()
        ->whereIn('email', ReviewerAccounts::emails())
        ->get()
        ->keyBy('email');

    expect($users)->toHaveCount(7);

    foreach (ReviewerAccounts::additionalDefinitions() as $definition) {
        $user = $users->get($definition['email']);

        expect($user)->not->toBeNull()
            ->and($user->role)->toBe(UserRole::Reviewer)
            ->and(Hash::check($definition['password'], $user->password))->toBeTrue()
            ->and($output)->toContain($definition['email']);
    }

    $split = ReviewerQueueSplitter::pendingSplit();

    expect($split)->toHaveCount(7)
        ->and(array_sum(array_column($split, 'pending')))->toBe($pending->count());

    foreach ($split as $row) {
        expect($output)->toContain($row['email']);
    }

    $fifth = $users->get(ReviewerAccounts::additionalDefinitions()[0]['email']);
    $ownedByFifth = $pending->filter(
        fn (MedicalRegistration $registration): bool => ReviewerQueueSplitter::owns($fifth, $registration),
    );

    expect($ownedByFifth->every(
        fn (MedicalRegistration $registration): bool => ((int) $registration->id % 7) === 4,
    ))->toBeTrue();
});
