<?php

namespace App\Console\Commands;

use App\Support\DatabaseCutover\DualWrite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('db:copy-sqlite-to-mysql
    {--source=sqlite : Source database connection (usually sqlite)}
    {--target=mysql_target : Target database connection (usually mysql_target)}
    {--live : Zero-downtime mode: upsert + prune while the site stays up (requires DB_DUAL_WRITE=true)}
    {--force : Skip confirmation prompts}
    {--passes=3 : Extra catch-up passes in --live mode after the main sync}')]
#[Description('Copy SQLite data to MySQL with the same migrations/IDs; use --live for zero-downtime cutover')]
class CopySqliteToMysqlCommand extends Command
{
    /**
     * Core application tables copied in foreign-key-safe order.
     *
     * @var list<string>
     */
    private const DATA_TABLES = [
        'users',
        'password_reset_tokens',
        'employees',
        'medical_registrations',
        'beneficiaries',
        'settings',
        'migrations',
    ];

    /**
     * Included in --live sync so in-progress form sessions survive the flip.
     *
     * @var list<string>
     */
    private const LIVE_EXTRA_TABLES = [
        'sessions',
    ];

    /**
     * Left empty on the target (safe to rebuild).
     *
     * @var list<string>
     */
    private const SKIP_DATA_TABLES = [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const UPSERT_KEYS = [
        'users' => ['id'],
        'password_reset_tokens' => ['email'],
        'employees' => ['id'],
        'medical_registrations' => ['id'],
        'beneficiaries' => ['id'],
        'settings' => ['id'],
        'migrations' => ['id'],
        'sessions' => ['id'],
    ];

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $target = (string) $this->option('target');
        $live = (bool) $this->option('live');

        if (! config()->has("database.connections.{$source}")) {
            $this->error("Unknown source connection [{$source}].");

            return self::FAILURE;
        }

        if (! config()->has("database.connections.{$target}")) {
            $this->error("Unknown target connection [{$target}].");

            return self::FAILURE;
        }

        if ($live) {
            if (! DualWrite::enabled()) {
                $this->error('Zero-downtime --live mode requires dual-write so new form saves hit both databases.');
                $this->line('Set DB_DUAL_WRITE=true in .env, then: php artisan config:clear');

                return self::FAILURE;
            }

            if (DualWrite::targetConnection() !== $target) {
                $this->error('DB_CUTOVER_TARGET / database.cutover.target must match --target ('.$target.').');

                return self::FAILURE;
            }
        } elseif (! app()->isDownForMaintenance()) {
            $this->error('Non-live copy needs maintenance mode, or use zero-downtime mode:');
            $this->line('  php artisan db:copy-sqlite-to-mysql --live --force');

            return self::FAILURE;
        }

        try {
            DB::connection($source)->getPdo();
            DB::connection($target)->getPdo();
        } catch (Throwable $exception) {
            $this->error('Could not connect to source/target database: '.$exception->getMessage());
            $this->line('For MySQL, set MYSQL_HOST, MYSQL_PORT, MYSQL_DATABASE, MYSQL_USERNAME, MYSQL_PASSWORD in .env');

            return self::FAILURE;
        }

        $tables = $live
            ? [...self::DATA_TABLES, ...self::LIVE_EXTRA_TABLES]
            : self::DATA_TABLES;

        $this->info(($live ? 'LIVE zero-downtime sync' : 'Maintenance copy').": {$source} → {$target}");
        $this->table(
            ['Table', 'Source rows'],
            collect($tables)
                ->map(fn (string $table): array => [
                    $table,
                    Schema::connection($source)->hasTable($table)
                        ? (string) DB::connection($source)->table($table)->count()
                        : 'missing',
                ])
                ->all()
        );

        if (! $this->option('force') && ! $this->confirm('Continue with copy/sync to target?', false)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $this->info('Running migrations on target (same migration files as SQLite)…');
        Artisan::call('migrate', [
            '--database' => $target,
            '--force' => true,
        ]);
        $this->line(trim(Artisan::output()) ?: 'Migrations OK');

        $targetDriver = DB::connection($target)->getDriverName();
        $this->withoutForeignKeys($target, $targetDriver, function () use ($live, $source, $target, $tables): void {
            if (! $live) {
                foreach (array_reverse([...self::DATA_TABLES, ...self::LIVE_EXTRA_TABLES, ...self::SKIP_DATA_TABLES]) as $table) {
                    if (Schema::connection($target)->hasTable($table)) {
                        DB::connection($target)->table($table)->delete();
                    }
                }
            } else {
                foreach (self::SKIP_DATA_TABLES as $table) {
                    if (Schema::connection($target)->hasTable($table)) {
                        DB::connection($target)->table($table)->delete();
                    }
                }
            }

            foreach ($tables as $table) {
                if (! Schema::connection($source)->hasTable($table)) {
                    $this->warn("Skipping missing source table [{$table}]");

                    continue;
                }

                if (! Schema::connection($target)->hasTable($table)) {
                    $this->error("Target is missing table [{$table}] after migrate.");

                    throw new \RuntimeException("Missing target table [{$table}]");
                }

                $copied = $this->syncTable($source, $target, $table, upsert: $live);
                $this->line(($live ? 'Synced' : 'Copied')." {$copied} row(s) for [{$table}]");
            }
        });

        if ($live) {
            $passes = max(1, (int) $this->option('passes'));
            $this->info("Running {$passes} catch-up pass(es) for writes that happened during sync…");

            for ($pass = 1; $pass <= $passes; $pass++) {
                $this->withoutForeignKeys($target, $targetDriver, function () use ($source, $target, $tables, $pass): void {
                    foreach ($tables as $table) {
                        if (! Schema::connection($source)->hasTable($table) || ! Schema::connection($target)->hasTable($table)) {
                            continue;
                        }

                        $copied = $this->syncTable($source, $target, $table, upsert: true);
                        $this->line("  Pass {$pass}: {$table} → {$copied} upserted, orphans pruned");
                    }
                });
            }
        }

        $mismatches = $this->countMismatches($source, $target, $tables);

        if ($mismatches !== []) {
            $this->error('Row count mismatch after sync:');
            foreach ($mismatches as $mismatch) {
                $this->line('  '.$mismatch);
            }

            return self::FAILURE;
        }

        $this->info('Verified: same migration schema path + matching row counts.');

        if ($live) {
            $this->newLine();
            $this->info('Site stayed UP. Flip to MySQL now (no artisan down):');
            $this->line('  1. In .env set DB_CONNECTION=mysql and DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD to the MySQL values');
            $this->line('  2. Set DB_DUAL_WRITE=false');
            $this->line('  3. php artisan config:clear && php artisan cache:clear');
            $this->line('  4. Smoke-test one login + HR page (users already mid-form keep their sessions)');
            $this->line('  5. Keep database.sqlite as backup for several days');
        } else {
            $this->line('  1. Point DB_* at MySQL, DB_CONNECTION=mysql');
            $this->line('  2. php artisan config:clear && php artisan up');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function countMismatches(string $source, string $target, array $tables): array
    {
        $mismatches = [];

        foreach ($tables as $table) {
            if (! Schema::connection($source)->hasTable($table) || ! Schema::connection($target)->hasTable($table)) {
                continue;
            }

            $sourceCount = DB::connection($source)->table($table)->count();
            $targetCount = DB::connection($target)->table($table)->count();

            if ($sourceCount !== $targetCount) {
                $mismatches[] = "{$table}: source={$sourceCount} target={$targetCount}";
            }
        }

        return $mismatches;
    }

    private function syncTable(string $source, string $target, string $table, bool $upsert): int
    {
        $copied = 0;
        $targetColumns = Schema::connection($target)->getColumnListing($table);
        $uniqueBy = self::UPSERT_KEYS[$table] ?? ['id'];
        $order = $this->orderColumn($source, $table);

        DB::connection($source)
            ->table($table)
            ->orderBy($order)
            ->chunk(500, function ($rows) use ($target, $table, $targetColumns, $uniqueBy, $upsert, &$copied): void {
                $payload = [];

                foreach ($rows as $row) {
                    $data = (array) $row;
                    $payload[] = array_intersect_key($data, array_flip($targetColumns));
                }

                if ($payload === []) {
                    return;
                }

                if ($upsert) {
                    $updateColumns = array_values(array_diff(array_keys($payload[0]), $uniqueBy));

                    if ($updateColumns === []) {
                        $updateColumns = $uniqueBy;
                    }

                    DB::connection($target)->table($table)->upsert($payload, $uniqueBy, $updateColumns);
                } else {
                    DB::connection($target)->table($table)->insert($payload);
                }

                $copied += count($payload);
            });

        if ($upsert) {
            $this->pruneOrphans($source, $target, $table, $uniqueBy);
        }

        return $copied;
    }

    /**
     * @param  list<string>  $uniqueBy
     */
    private function pruneOrphans(string $source, string $target, string $table, array $uniqueBy): void
    {
        $key = $uniqueBy[0];

        $sourceKeys = DB::connection($source)->table($table)->pluck($key)->all();

        $query = DB::connection($target)->table($table);

        if ($sourceKeys === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn($key, $sourceKeys)->delete();
    }

    private function orderColumn(string $connection, string $table): string
    {
        $columns = Schema::connection($connection)->getColumnListing($table);

        if (in_array('id', $columns, true)) {
            return 'id';
        }

        return $columns[0] ?? 'rowid';
    }

    private function withoutForeignKeys(string $connection, string $driver, callable $callback): void
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::connection($connection)->statement('PRAGMA foreign_keys = OFF');
        }

        try {
            $callback();
        } finally {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::connection($connection)->statement('PRAGMA foreign_keys = ON');
            }
        }
    }
}
