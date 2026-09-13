<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ReviewerAccounts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reviewers:create')]
#[Description('Create the four reviewer accounts that can only access the pending review queue')]
class CreateReviewersCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Reviewer accounts for /admin pending reviews');
        $this->newLine();

        foreach (ReviewerAccounts::definitions() as $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => $definition['password'],
                    'email_verified_at' => now(),
                    'role' => UserRole::Reviewer,
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
        $this->components->success('Four reviewer accounts are ready.');

        return self::SUCCESS;
    }
}
