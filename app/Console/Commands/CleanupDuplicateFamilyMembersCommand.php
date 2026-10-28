<?php

namespace App\Console\Commands;

use App\Services\DuplicateFamilyMemberCleaner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('beneficiaries:cleanup-duplicates {--dry-run : Show what would change without changing data} {--except=* : Beneficiary IDs to leave untouched} {--force : Skip the confirmation prompt}')]
#[Description('Cancel family members who are already employees, and keep only one record when the same person was added under multiple employees')]
class CleanupDuplicateFamilyMembersCommand extends Command
{
    public function handle(DuplicateFamilyMemberCleaner $cleaner): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (
            ! $dryRun
            && ! $this->option('force')
            && ! $this->confirm('Cancel/delete duplicate family member records?', false)
        ) {
            $this->components->warn('Cancelled.');

            return self::SUCCESS;
        }

        $exceptIds = collect($this->option('except'))
            ->flatMap(fn (string $value): array => explode(',', $value))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter()
            ->values()
            ->all();

        $result = $cleaner->cleanup(dryRun: $dryRun, exceptIds: $exceptIds);

        foreach ($result['flagged_for_review'] as $row) {
            $this->components->warn(sprintf(
                '%s #%d %s: national ID belongs to employee %s, but the name is different. Left unchanged for human review.',
                $dryRun ? 'Would flag' : 'Flagged',
                $row['id'],
                $row['name'],
                $row['employee_name'],
            ));
        }

        if ($result['records'] === []) {
            $this->components->info('No duplicate family members found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'National ID', 'Reason', 'Printed', 'Registration', 'Employee'],
            collect($result['records'])->map(fn (array $row): array => [
                $row['id'],
                $row['name'],
                $row['national_id'] ?? $row['passport_number'] ?? '—',
                $row['reason'],
                $row['printed'] ? 'yes' : 'no',
                $row['registration_id'] ?? '—',
                $row['employee_id'] ?? '—',
            ])->all(),
        );

        $this->components->info(sprintf(
            '%s %d employee-as-family record(s) and %s %d duplicate family record(s).',
            $dryRun ? 'Would cancel' : 'Cancelled',
            $result['employee_as_beneficiary'],
            $dryRun ? 'would delete' : 'deleted',
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
