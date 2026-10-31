<?php

namespace App\Observers;

use App\Support\DatabaseCutover\DualWrite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DualWriteObserver
{
    public function created(Model $model): void
    {
        $this->mirrorUpsert($model);
    }

    public function updated(Model $model): void
    {
        $this->mirrorUpsert($model);
    }

    public function deleted(Model $model): void
    {
        if (! DualWrite::enabled()) {
            return;
        }

        if ($this->wasSoftDeleted($model)) {
            $this->mirrorUpsert($model);

            return;
        }

        $this->mirrorHardDelete($model);
    }

    public function forceDeleted(Model $model): void
    {
        if (! DualWrite::enabled()) {
            return;
        }

        $this->mirrorHardDelete($model);
    }

    public function restored(Model $model): void
    {
        $this->mirrorUpsert($model);
    }

    private function wasSoftDeleted(Model $model): bool
    {
        return method_exists($model, 'isForceDeleting')
            && method_exists($model, 'getDeletedAtColumn')
            && ! $model->isForceDeleting();
    }

    private function mirrorHardDelete(Model $model): void
    {
        $this->safe(function () use ($model): void {
            DB::connection(DualWrite::targetConnection())
                ->table($model->getTable())
                ->where($model->getKeyName(), $model->getKey())
                ->delete();
        }, $model, 'delete');
    }

    private function mirrorUpsert(Model $model): void
    {
        if (! DualWrite::enabled()) {
            return;
        }

        $this->safe(function () use ($model): void {
            $attributes = $model->getAttributes();
            $key = $model->getKeyName();

            DB::connection(DualWrite::targetConnection())
                ->table($model->getTable())
                ->upsert(
                    [$attributes],
                    [$key],
                    array_values(array_diff(array_keys($attributes), [$key])),
                );
        }, $model, 'upsert');
    }

    private function safe(callable $callback, Model $model, string $operation): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::error('Dual-write to MySQL failed during SQLite cutover.', [
                'operation' => $operation,
                'model' => $model::class,
                'key' => $model->getKey(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
