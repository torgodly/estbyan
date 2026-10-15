<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\CardDeliveryAccounts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('card-delivery:create-accounts')]
#[Description('Create the eastern and western region accounts that can only access card delivery')]
class CreateCardDeliveryAccountsCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Card delivery accounts for /admin/deliver-insurance-cards');
        $this->newLine();

        foreach (CardDeliveryAccounts::definitions() as $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => $definition['password'],
                    'email_verified_at' => now(),
                    'role' => UserRole::CardDelivery,
                ],
            );

            $this->line(sprintf(
                '  %s  %s  %s',
                $user->wasRecentlyCreated ? 'Created' : 'Updated',
                $definition['email'],
                $definition['password'],
            ));
        }

        $this->newLine();
        $this->components->success(sprintf(
            '%d card delivery accounts are ready.',
            count(CardDeliveryAccounts::definitions()),
        ));

        return self::SUCCESS;
    }
}
