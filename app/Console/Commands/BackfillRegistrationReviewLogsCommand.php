<?php

namespace App\Console\Commands;

use App\Support\RegistrationReviewLogBackfill;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('review-logs:backfill')]
#[Description('Copy existing approve and decline decisions into the review history log')]
class BackfillRegistrationReviewLogsCommand extends Command
{
    public function handle(): int
    {
        $created = RegistrationReviewLogBackfill::run();

        $this->components->success(sprintf(
            'Backfilled %d existing approve/decline decision%s into the review history.',
            $created,
            $created === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }
}
