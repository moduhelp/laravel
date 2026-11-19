<?php

namespace ModuHelp\Laravel;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use ModuHelp\Laravel\Http\Middleware\InjectWidget;
use Throwable;

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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'moduhelp');
        Blade::componentNamespace('ModuHelp\\Laravel\\View\\Components', 'moduhelp');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/moduhelp.php' => config_path('moduhelp.php'),
            ], 'moduhelp-config');
        }

        $this->registerMiddleware();
    }

    /**
     * Kernel::appendMiddlewareToGroup survives the kernel re-syncing its groups
     * (Router::pushMiddlewareToGroup does not) and works with both the
     * bootstrap/app.php style and a legacy App\Http\Kernel. It throws when
     * the app has no "web" group, which must never break the host.
     *
     * The middleware is registered whenever the package is enabled, whatever
     * moduhelp.inject says: that flag is read per request and only decides
     * whether it injects. Registration waits for the HTTP kernel to be resolved,
     * so console and queue processes never build it.
     */
    private function registerMiddleware(): void
    {
        $this->callAfterResolving(Kernel::class, function (Kernel $kernel): void {
            try {
                if (! $this->app->make(ModuHelp::class)->enabled()) {
                    return;
                }

                $kernel->appendMiddlewareToGroup('web', InjectWidget::class);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
