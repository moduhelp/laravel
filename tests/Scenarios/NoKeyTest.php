<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use ModuHelp\Laravel\Http\Middleware\InjectWidget;
use ModuHelp\Laravel\Tests\Fixtures\Cases\NoKeyCase;
use ModuHelp\Laravel\Tests\Fixtures\TestUser;

uses(NoKeyCase::class);

it('stays inert and untouched when the key is missing', function () {
    $this->actingAs(new TestUser(['id' => 7, 'email' => 'a@example.com', 'name' => 'Ana']));
    $page = '<html><head></head><body></body></html>';
    Route::middleware('web')->get('/page', fn () => response($page));

    expect(app(Kernel::class)->getMiddlewareGroups()['web'])->not->toContain(InjectWidget::class)
        ->and($this->get('/page')->getContent())->toBe($page);
});

it('leaves a page untouched even if the middleware is attached by hand without a key', function () {
    $this->actingAs(new TestUser(['id' => 7, 'email' => 'a@example.com', 'name' => 'Ana']));
    $page = '<html><head></head><body></body></html>';
    Route::middleware(['web', InjectWidget::class])->get('/page', fn () => response($page));

    expect($this->get('/page')->getContent())->toBe($page);
});
