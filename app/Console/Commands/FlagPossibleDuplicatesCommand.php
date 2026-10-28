<?php

namespace App\Console\Commands;

use App\Models\Beneficiary;
use App\Services\PossibleDuplicateDetector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('review:flag-possible-duplicates {--dry-run : Show what would be flagged without saving}')]
#[Description('Flag people who are probably registered twice (ID typo or same name) for manual review. Nothing is deleted.')]
class FlagPossibleDuplicatesCommand extends Command
{
    public function handle(PossibleDuplicateDetector $detector): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $flags = $dryRun ? $detector->detect() : $detector->flag();

        if ($flags === []) {
            $this->components->info('No possible duplicates found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Type', 'ID', 'Name', 'Reason', 'Note'],
            collect($flags)->map(fn (array $flag): array => [
                $flag['person'] instanceof Beneficiary ? 'family' : 'employee',
                $flag['person']->getKey(),
                trim((string) $flag['person']->full_name),
                $flag['reason']->value,
                $flag['note'],
            ])->all(),
        );

        $this->components->info(sprintf(
            '%s %d person(s) for manual review.',
            $dryRun ? 'Would flag' : 'Flagged',
            count($flags),
        ));

        return self::SUCCESS;
    }
}
