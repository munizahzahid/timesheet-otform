<?php

namespace App\Providers;

use App\Support\WindowsFilesystem;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Replace default Filesystem with a Windows-safe version that
        // retries rename() on file-lock errors (XAMPP + antivirus/opcache).
        if (PHP_OS_FAMILY === 'Windows') {
            $this->app->singleton('files', fn() => new WindowsFilesystem);
            $this->app->alias('files', Filesystem::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
