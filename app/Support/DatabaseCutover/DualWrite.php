<?php

namespace App\Support\DatabaseCutover;

use Illuminate\Database\Eloquent\Model;

class DualWrite
{
    public static function enabled(): bool
    {
        return (bool) config('database.cutover.dual_write', false);
    }

    public static function targetConnection(): string
    {
        return (string) config('database.cutover.target', 'mysql_target');
    }

    /**
     * @return list<class-string<Model>>
     */
    public static function models(): array
    {
        return config('database.cutover.models', []);
    }
}
