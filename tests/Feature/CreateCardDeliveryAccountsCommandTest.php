<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\CardDeliveryAccounts;
use Illuminate\Support\Facades\Artisan;

it('creates the eastern and western card delivery accounts', function () {
    Artisan::call('card-delivery:create-accounts');

    foreach (CardDeliveryAccounts::definitions() as $definition) {
        $user = User::query()->where('email', $definition['email'])->first();

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe($definition['name'])
            ->and($user->role)->toBe(UserRole::CardDelivery)
            ->and($user->isCardDelivery())->toBeTrue()
            ->and($user->canAccessFullAdmin())->toBeFalse()
            ->and($user->canDeliverInsuranceCards())->toBeTrue();
    }

    expect(Artisan::output())->toContain('2 card delivery accounts are ready.');
});
