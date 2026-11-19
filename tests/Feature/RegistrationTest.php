<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use ModuHelp\Laravel\Http\Middleware\InjectWidget;
use ModuHelp\Laravel\ModuHelpServiceProvider;

function injectCount(): int
{
    return count(array_keys(app(Kernel::class)->getMiddlewareGroups()['web'], InjectWidget::class, true));
}

it('appends to the web group when enabled', function () {
    expect(injectCount())->toBe(1);
});

it('does not append twice when the provider boots again', function () {
    (new ModuHelpServiceProvider($this->app))->boot();
    (new ModuHelpServiceProvider($this->app))->boot();

    expect(injectCount())->toBe(1);
});

it('registers on the router group so web routes run it', function () {
    expect(Route::getMiddlewareGroups()['web'])->toContain(InjectWidget::class);
});
