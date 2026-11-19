<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use ModuHelp\Laravel\Http\Middleware\InjectWidget;
use ModuHelp\Laravel\Tests\Fixtures\Cases\InjectOffCase;
use ModuHelp\Laravel\Tests\Fixtures\TestUser;

uses(InjectOffCase::class);

beforeEach(fn () => $this->actingAs(new TestUser(['id' => 7, 'email' => 'a@example.com', 'name' => 'Ana'])));

it('still registers the middleware when inject is false', function () {
    expect(app(Kernel::class)->getMiddlewareGroups()['web'])->toContain(InjectWidget::class);
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

it('hardens a component page when inject is false', function () {
    Route::middleware('web')->get('/page', fn () => response(
        Blade::render('<html><head><x-moduhelp::widget /></head><body></body></html>'),
        200,
        ['Cache-Control' => 'public, max-age=600', 'ETag' => '"abc"'],
    ));

    $response = $this->get('/page');
    $cache = $response->headers->get('Cache-Control');

    expect(substr_count($response->getContent(), '<meta name="moduhelp-widget"'))->toBe(1)
        ->and($cache)->toContain('private')->toContain('no-store')->not->toContain('public')
        ->and($response->headers->has('ETag'))->toBeFalse();
});
