<?php

use Illuminate\Contracts\Http\Kernel;
use ModuHelp\Laravel\Http\Middleware\InjectWidget;
use ModuHelp\Laravel\Tests\Fixtures\Cases\NoWebGroupCase;

uses(NoWebGroupCase::class);

it('does not throw when the web group is missing', function () {
    $kernel = app(Kernel::class);

    expect($kernel->getMiddlewareGroups())->not->toHaveKey('web')
        ->and(fn () => $kernel->appendMiddlewareToGroup('web', InjectWidget::class))
        ->toThrow(InvalidArgumentException::class)
        ->and($kernel->getMiddlewareGroups())->not->toHaveKey('web');
});
