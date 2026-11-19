<?php

namespace ModuHelp\Laravel;

use Illuminate\Support\ServiceProvider;

class ModuHelpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/moduhelp.php', 'moduhelp');

        $this->app->singleton(ModuHelp::class);
        $this->app->alias(ModuHelp::class, 'moduhelp');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/moduhelp.php' => config_path('moduhelp.php'),
            ], 'moduhelp-config');
        }
    }
}
