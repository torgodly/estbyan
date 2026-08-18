<?php

namespace App\Providers;

use App\Observers\DualWriteObserver;
use App\Session\DualWriteDatabaseSessionHandler;
use App\Support\DatabaseCutover\DualWrite;
use App\Support\RegistrationDocuments;
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
        $this->configureRegistrationUploads();

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

    /**
     * Phone scans of family booklets exceed PHP/Livewire defaults (2MB / 12MB).
     * upload_max_filesize and post_max_size are PHP_INI_PERDIR — also set in
     * public/.user.ini and public/.htaccess for FPM/Apache.
     */
    protected function configureRegistrationUploads(): void
    {
        $maxKilobytes = RegistrationDocuments::maxKilobytes();
        $temporaryMaxKilobytes = max($maxKilobytes, 64 * 1024);

        @ini_set('max_execution_time', '180');
        @ini_set('max_input_time', '180');
        @ini_set('memory_limit', '256M');
        @ini_set('upload_max_filesize', '64M');
        @ini_set('post_max_size', '64M');

        config([
            'livewire.temporary_file_upload.disk' => 'local',
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.$temporaryMaxKilobytes],
            'livewire.temporary_file_upload.max_upload_time' => 30,
        ]);
    }
}
