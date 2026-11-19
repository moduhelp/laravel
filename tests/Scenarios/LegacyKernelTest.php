<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use ModuHelp\Laravel\Http\Middleware\InjectWidget;
use ModuHelp\Laravel\Tests\Fixtures\Cases\LegacyKernelCase;
use ModuHelp\Laravel\Tests\Fixtures\LegacyKernel;
use ModuHelp\Laravel\Tests\Fixtures\TestUser;

uses(LegacyKernelCase::class);

it('works with a legacy Http Kernel', function () {
    $this->actingAs(new TestUser(['id' => 7, 'email' => 'a@example.com', 'name' => 'Ana']));
    Route::middleware('web')->get('/page', fn () => response('<html><head></head><body></body></html>'));

    $groups = app(Kernel::class)->getMiddlewareGroups();

    expect(app(Kernel::class))->toBeInstanceOf(LegacyKernel::class)
        ->and(array_keys($groups['web'], InjectWidget::class, true))->toHaveCount(1)
        ->and($this->get('/page')->getContent())->toContain('name="moduhelp-widget"');
});
