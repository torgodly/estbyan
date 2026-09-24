<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ReviewerAccounts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reviewers:expand')]
#[Description('Create any missing reviewer accounts; every reviewer sees the full pending queue')]
class ExpandReviewersCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Creating reviewer accounts');
        $this->newLine();

        $additionalEmails = array_column(ReviewerAccounts::additionalDefinitions(), 'email');

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

            $label = in_array($definition['email'], $additionalEmails, true) ? 'extra' : 'existing';

            $this->line(sprintf(
                '  [%s] %s  %s  %s',
                $label,
                $user->wasRecentlyCreated ? 'Created' : 'Updated',
                $definition['email'],
                $definition['password'],
            ));
        }

        $this->newLine();
        $this->components->success(sprintf(
            '%d reviewer accounts are ready. Every reviewer can see every pending request.',
            count(ReviewerAccounts::definitions()),
        ));

        return self::SUCCESS;
    }
}
