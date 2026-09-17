<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ReviewerAccounts;
use App\Support\ReviewerQueueSplitter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reviewers:expand')]
#[Description('Create three extra reviewer accounts and resplit pending reviews across all reviewers')]
class ExpandReviewersCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Creating the three extra reviewer accounts');
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

        $split = ReviewerQueueSplitter::pendingSplit();
        $totalPending = array_sum(array_column($split, 'pending'));

        $this->newLine();
        $this->components->info(sprintf(
            'Pending reviews resplit across %d reviewer accounts (%d submitted)',
            count($split),
            $totalPending,
        ));

        $this->table(
            ['Reviewer', 'Email', 'Pending'],
            array_map(fn (array $row): array => [
                $row['name'],
                $row['email'],
                (string) $row['pending'],
            ], $split),
        );

        $this->components->success('Reviewer accounts are ready and the queue is split by record id.');

        return self::SUCCESS;
    }
}
