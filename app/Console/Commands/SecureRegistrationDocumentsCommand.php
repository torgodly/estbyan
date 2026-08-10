<?php

namespace App\Console\Commands;

use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Support\RegistrationDocuments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SecureRegistrationDocumentsCommand extends Command
{
    protected $signature = 'registrations:secure-documents {--dry-run : Show what would move without writing}';

    protected $description = 'Move registration documents from the public disk into private local storage';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $public = Storage::disk('public');
        $private = RegistrationDocuments::disk();
        $moved = 0;
        $skipped = 0;

        MedicalRegistration::query()
            ->where(function ($query): void {
                $query->whereNotNull('family_status_document_path')
                    ->orWhereNotNull('employee_photo_path');
            })
            ->orderBy('id')
            ->each(function (MedicalRegistration $registration) use ($public, $private, $dryRun, &$moved, &$skipped): void {
                foreach (['family_status_document_path', 'employee_photo_path'] as $attribute) {
                    $path = $registration->{$attribute};

                    if (! filled($path)) {
                        continue;
                    }

                    if ($private->exists($path)) {
                        $skipped++;

                        continue;
                    }

                    if (! $public->exists($path)) {
                        $this->warn("Missing public file for registration #{$registration->id}: {$path}");
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        $private->put($path, $public->get($path));
                        $public->delete($path);
                    }

                    $moved++;
                    $this->line(($dryRun ? '[dry-run] ' : '')."Moved {$path}");
                }
            });

        Beneficiary::query()
            ->whereNotNull('photo_path')
            ->orderBy('id')
            ->each(function (Beneficiary $beneficiary) use ($public, $private, $dryRun, &$moved, &$skipped): void {
                $path = $beneficiary->photo_path;

                if (! filled($path)) {
                    return;
                }

                if ($private->exists($path)) {
                    $skipped++;

                    return;
                }

                if (! $public->exists($path)) {
                    $this->warn("Missing public beneficiary photo #{$beneficiary->id}: {$path}");
                    $skipped++;

                    return;
                }

                if (! $dryRun) {
                    $private->put($path, $public->get($path));
                    $public->delete($path);
                }

                $moved++;
                $this->line(($dryRun ? '[dry-run] ' : '')."Moved {$path}");
            });

        $this->info("Done. Moved: {$moved}. Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
