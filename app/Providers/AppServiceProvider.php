<?php

namespace App\Providers;

use App\Observers\DualWriteObserver;
use App\Session\DualWriteDatabaseSessionHandler;
use App\Support\DatabaseCutover\DualWrite;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! DualWrite::enabled()) {
            return;
        }

        foreach (DualWrite::models() as $model) {
            $model::observe(DualWriteObserver::class);
        }

        Session::extend('dual-database', function ($app) {
            $connectionName = $app['config']['session.connection'];
            $table = $app['config']['session.table'];
            $lifetime = $app['config']['session.lifetime'];

            return new DualWriteDatabaseSessionHandler(
                $app['db']->connection($connectionName),
                $table,
                $lifetime,
                $app['db']->connection(DualWrite::targetConnection()),
                $app,
            );
        });

        if ($this->app['config']->get('session.driver') === 'database') {
            $this->app['config']->set('session.driver', 'dual-database');
        }
    }
}
