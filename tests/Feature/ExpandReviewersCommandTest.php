<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ReviewerAccounts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('creates missing reviewer accounts without splitting the queue', function () {
    foreach (array_slice(ReviewerAccounts::definitions(), 0, 4) as $definition) {
        User::factory()->reviewer()->create([
            'name' => $definition['name'],
            'email' => $definition['email'],
            'password' => $definition['password'],
        ]);
    }

    Artisan::call('reviewers:expand');
    $output = Artisan::output();

    $users = User::query()
        ->whereIn('email', ReviewerAccounts::emails())
        ->get()
        ->keyBy('email');

    expect($users)->toHaveCount(count(ReviewerAccounts::definitions()));

    foreach (ReviewerAccounts::additionalDefinitions() as $definition) {
        $user = $users->get($definition['email']);

        expect($user)->not->toBeNull()
            ->and($user->role)->toBe(UserRole::Reviewer)
            ->and(Hash::check($definition['password'], $user->password))->toBeTrue()
            ->and($output)->toContain($definition['email']);
    }

    expect($output)->toContain('Every reviewer can see every pending request.')
        ->and($users->get('reviewer8@smartcare.com.ly'))->not->toBeNull();
});
