<?php

namespace App\Session;

use App\Support\DatabaseCutover\DualWrite;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class DualWriteDatabaseSessionHandler extends DatabaseSessionHandler
{
    public function __construct(
        ConnectionInterface $connection,
        string $table,
        int $minutes,
        protected ConnectionInterface $mirrorConnection,
        ?Container $container = null,
    ) {
        parent::__construct($connection, $table, $minutes, $container);
    }

    public function write($sessionId, $data): bool
    {
        $result = parent::write($sessionId, $data);

        $this->mirrorSession($sessionId);

        return $result;
    }

    public function destroy($sessionId): bool
    {
        $result = parent::destroy($sessionId);

        if (DualWrite::enabled()) {
            try {
                $this->mirrorConnection->table($this->table)->where('id', $sessionId)->delete();
            } catch (Throwable $exception) {
                Log::error('Dual-write session destroy failed.', [
                    'session_id' => $sessionId,
                    'message' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }

        return $result;
    }

    private function mirrorSession(string $sessionId): void
    {
        if (! DualWrite::enabled()) {
            return;
        }

        try {
            $row = $this->connection->table($this->table)->where('id', $sessionId)->first();

            if ($row === null) {
                return;
            }

            $payload = (array) $row;
            $columns = array_keys($payload);

            $this->mirrorConnection->table($this->table)->upsert(
                [$payload],
                ['id'],
                array_values(array_diff($columns, ['id'])),
            );
        } catch (Throwable $exception) {
            Log::error('Dual-write session write failed.', [
                'session_id' => $sessionId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
