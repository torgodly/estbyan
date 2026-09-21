<?php

namespace App\Console\Commands;

use App\Services\DuplicateFamilyMemberCleaner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('beneficiaries:cleanup-duplicates {--dry-run : Show what would be deleted without changing data} {--force : Skip the confirmation prompt}')]
#[Description('Remove family members who are already employees, and keep only one record when the same person was added under multiple employees')]
class CleanupDuplicateFamilyMembersCommand extends Command
{
    public function handle(DuplicateFamilyMemberCleaner $cleaner): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (
            ! $dryRun
            && ! $this->option('force')
            && ! $this->confirm('Delete duplicate family member records?', false)
        ) {
            $this->components->warn('Cancelled.');

            return self::SUCCESS;
        }

        $result = $cleaner->cleanup(dryRun: $dryRun);

        if ($result['deleted'] === []) {
            $this->components->info('No duplicate family members found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'National ID', 'Reason', 'Registration', 'Employee'],
            collect($result['deleted'])->map(fn (array $row): array => [
                $row['id'],
                $row['name'],
                $row['national_id'] ?? $row['passport_number'] ?? '—',
                $row['reason'],
                $row['registration_id'] ?? '—',
                $row['employee_id'] ?? '—',
            ])->all(),
        );

        $this->components->info(sprintf(
            '%s %d employee-as-family record(s) and %d duplicate family record(s).',
            $dryRun ? 'Would delete' : 'Deleted',
            $result['employee_as_beneficiary'],
            $result['duplicate_beneficiaries'],
        ));

        if (! $dryRun && $result['registrations_updated'] > 0) {
            $this->components->info(sprintf(
                'Updated beneficiaries_count on %d registration(s).',
                $result['registrations_updated'],
            ));
        }

        return self::SUCCESS;
    }
}
