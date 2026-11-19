<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use ModuHelp\Laravel\Http\Middleware\InjectWidget;
use ModuHelp\Laravel\Tests\Fixtures\Cases\InjectOffCase;
use ModuHelp\Laravel\Tests\Fixtures\TestUser;

uses(InjectOffCase::class);

beforeEach(fn () => $this->actingAs(new TestUser(['id' => 7, 'email' => 'a@example.com', 'name' => 'Ana'])));

it('does not register when inject is false', function () {
    expect(app(Kernel::class)->getMiddlewareGroups()['web'])->not->toContain(InjectWidget::class);
});

it('does not inject a page when inject is false', function () {
    $page = '<html><head></head><body></body></html>';
    Route::middleware('web')->get('/page', fn () => response($page));

    expect($this->get('/page')->getContent())->toBe($page);
});

it('still renders the component exactly once when inject is false', function () {
    Route::middleware('web')->get('/page', fn () => response(
        Blade::render('<html><head><x-moduhelp::widget /></head><body></body></html>')
    ));

    expect(substr_count($this->get('/page')->getContent(), '<meta name="moduhelp-widget"'))->toBe(1);
});
