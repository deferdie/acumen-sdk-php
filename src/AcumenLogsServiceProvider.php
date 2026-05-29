<?php

namespace AcumenLogs;

use Illuminate\Support\ServiceProvider;

class AcumenLogsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/acumenlogs.php', 'acumenlogs');

        $this->app->singleton(AcumenLogs::class, function ($app) {
            return new AcumenLogs($app['config']['acumenlogs']);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/acumenlogs.php' => config_path('acumenlogs.php'),
            ], 'acumenlogs-config');
        }
    }
}
