<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use ModuHelp\Laravel\Facades\ModuHelp;
use ModuHelp\Laravel\ModuHelp as Manager;
use ModuHelp\Laravel\Tests\Fixtures\TestUser;
use ModuHelp\Laravel\Tests\Fixtures\ThrowingModuHelp;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

const PAGE = '<html><head><title>x</title></head><body>hi</body></html>';
const MARKER = '<meta name="moduhelp-widget"';

function pageRoute(?Closure $respond = null, string $uri = '/page', string $method = 'get'): void
{
    Route::middleware('web')->{$method}($uri, $respond ?? fn () => response(PAGE));
}

function signedIn(): TestUser
{
    return new TestUser(['id' => 7, 'email' => 'ana@example.com', 'name' => 'Ana']);
}

function identityMeta(string $html): ?string
{
    return preg_match('/name="moduhelp-identity" content="([^"]*)"/', $html, $m) === 1 ? $m[1] : null;
}

beforeEach(fn () => $this->actingAs(signedIn()));

it('injects before </head>', function () {
    pageRoute();

    $body = $this->get('/page')->assertOk()->getContent();

    expect(substr_count($body, MARKER))->toBe(1)
        ->and(strpos($body, MARKER))->toBeLessThan(strpos($body, '</head>'))
        ->and($body)->toStartWith('<html><head><title>x</title>')
        ->and($body)->toEndWith('</head><body>hi</body></html>')
        ->and(identityMeta($body))->not->toBe('')->not->toBeNull();
});

it('matches </HEAD> case-insensitively and only the first one', function () {
    pageRoute(fn () => response('<HTML><HEAD></HEAD><body><p></head></p></body></HTML>'));

    $body = $this->get('/page')->getContent();

    expect(substr_count($body, MARKER))->toBe(1)
        ->and(strpos($body, MARKER))->toBeLessThan(strpos($body, '</HEAD>'))
        ->and($body)->toEndWith('</HEAD><body><p></head></p></body></HTML>');
});

it('injects a wire:navigate request', function () {
    pageRoute();

    $body = $this->get('/page', ['X-Livewire-Navigate' => '1'])->getContent();

    expect($body)->toContain(MARKER);
});

it('skips a livewire update', function () {
    pageRoute(uri: '/livewire/update', method: 'post');

    $body = $this->post('/livewire/update', [], ['X-Livewire' => ''])->getContent();

    expect($body)->toBe(PAGE);
});

it('skips GET with an empty X-Livewire header', function () {
    pageRoute();

    expect($this->get('/page', ['X-Livewire' => ''])->getContent())->toBe(PAGE);
});

it('skips the default livewire/* except entry on GET', function () {
    pageRoute(uri: 'livewire/preview-file/x');

    expect($this->get('/livewire/preview-file/x')->getContent())->toBe(PAGE);
});

it('skips an except path', function () {
    pageRoute(uri: 'horizon/dashboard');

    expect($this->get('/horizon/dashboard')->getContent())->toBe(PAGE);
});

it('skips an ajax request', function () {
    pageRoute();

    expect($this->get('/page', ['X-Requested-With' => 'XMLHttpRequest'])->getContent())->toBe(PAGE);
});

it('skips POST', function () {
    pageRoute(method: 'post');

    expect($this->post('/page')->getContent())->toBe(PAGE);
});

it('skips HEAD', function () {
    pageRoute();

    $response = $this->call('HEAD', '/page');

    expect($response->getContent())->toBe('');
});

it('skips a guest', function () {
    pageRoute();
    auth()->logout();

    expect($this->get('/page')->getContent())->toBe(PAGE);
});

it('skips when the route called ModuHelp::skip()', function () {
    pageRoute(function () {
        ModuHelp::skip();

        return response(PAGE);
    });

    expect($this->get('/page')->getContent())->toBe(PAGE);
});

/** @return array<string, array{Closure(): mixed, string}> */
function nonPageCases(): array
{
    return [
        'json' => [fn () => new JsonResponse(['a' => 1]), '{"a":1}'],
        'redirect' => [fn () => new RedirectResponse('/elsewhere'), (new RedirectResponse('/elsewhere'))->getContent()],
        'text/plain' => [fn () => response(PAGE, 200, ['Content-Type' => 'text/plain']), PAGE],
        'gzip' => [fn () => response(PAGE, 200, ['Content-Encoding' => 'gzip']), PAGE],
        'status 404' => [fn () => response(PAGE, 404), PAGE],
        'status 500' => [fn () => response(PAGE, 500), PAGE],
        'public cache' => [fn () => response(PAGE, 200, ['Cache-Control' => 'public, max-age=600']), PAGE],
        's-maxage' => [fn () => response(PAGE, 200, ['Cache-Control' => 'max-age=0, s-maxage=60']), PAGE],
        'uppercase PUBLIC' => [fn () => response(PAGE, 200, ['Cache-Control' => 'PUBLIC']), PAGE],
    ];
}

it('skips a non-page response untouched', function (string $case) {
    [$make, $expected] = nonPageCases()[$case];
    Route::middleware('web')->get('/page', $make);

    expect($this->get('/page')->getContent())->toBe($expected);
})->with(array_map(fn ($case) => [$case], array_combine(array_keys(nonPageCases()), array_keys(nonPageCases()))));

it('skips a streamed response', function () {
    pageRoute(fn () => response()->stream(function () {
        echo PAGE;
    }, 200, ['Content-Type' => 'text/html']));

    ob_start();
    $response = $this->get('/page');
    $streamed = $response->streamedContent();
    ob_end_clean();

    expect($streamed)->toBe(PAGE);
});

it('skips a file download', function () {
    $path = tempnam(sys_get_temp_dir(), 'mh');
    file_put_contents($path, PAGE);
    pageRoute(fn () => response()->download($path, 'page.html', ['Content-Type' => 'text/html']));

    $response = $this->get('/page');

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))->toBe(PAGE)
        ->and($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);

    @unlink($path);
});

it('leaves the cache headers of a skipped public page alone', function () {
    pageRoute(fn () => response(PAGE, 200, ['Cache-Control' => 'public, max-age=600', 'ETag' => '"abc"']));

    $response = $this->get('/page');

    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('ETag'))->toBe('"abc"');
});

it('rewrites cache headers after injecting a token', function () {
    pageRoute(fn () => response(PAGE, 200, [
        'ETag' => '"abc"',
        'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
        'Content-Length' => (string) strlen(PAGE),
        'Cache-Control' => 'max-age=600',
    ]));

    $response = $this->get('/page');
    $cache = $response->headers->get('Cache-Control');

    expect($response->getContent())->toContain(MARKER)
        ->and($cache)->toContain('no-store')->toContain('private')->not->toContain('public')->not->toContain('max-age')
        ->and($response->headers->has('ETag'))->toBeFalse()
        ->and($response->headers->has('Last-Modified'))->toBeFalse()
        ->and($response->headers->has('Content-Length'))->toBeFalse();
});

it('leaves cache headers alone when anonymous but drops a stale Content-Length', function () {
    config()->set('moduhelp.identity_secret', null);
    pageRoute(fn () => response(PAGE, 200, [
        'ETag' => '"abc"',
        'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
        'Content-Length' => (string) strlen(PAGE),
        'Cache-Control' => 'max-age=600',
    ]));

    $response = $this->get('/page');

    expect($response->getContent())->toContain(MARKER)
        ->and(identityMeta($response->getContent()))->toBe('')
        ->and($response->headers->get('ETag'))->toBe('"abc"')
        ->and($response->headers->get('Last-Modified'))->not->toBeNull()
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=600')->not->toContain('no-store')
        ->and($response->headers->has('Content-Length'))->toBeFalse();
});

it('injects once when the component is already in the page', function () {
    pageRoute(fn () => response(Blade::render('<html><head><x-moduhelp::widget /></head><body>hi</body></html>')));

    $body = $this->get('/page')->getContent();

    expect(substr_count($body, MARKER))->toBe(1);
});

it('leaves a page untouched when the body has no </head>', function () {
    pageRoute(fn () => response('<p>fragment</p>'));

    expect($this->get('/page')->getContent())->toBe('<p>fragment</p>');
});

it('leaves an empty body untouched', function () {
    pageRoute(fn () => response('', 200, ['Content-Type' => 'text/html']));

    expect($this->get('/page')->getContent())->toBe('');
});

it('returns the original response when render throws', function () {
    Exceptions::fake();
    $this->app->singleton(Manager::class, fn ($app) => new ThrowingModuHelp($app['config']));
    pageRoute();

    $response = $this->get('/page');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe(PAGE);
    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'render blew up');
});

it('carries no token on a request after logout in the same process', function () {
    pageRoute(fn () => response(PAGE, 200, ['ETag' => '"abc"']));

    $first = $this->get('/page');
    expect(identityMeta($first->getContent()))->not->toBe('')->not->toBeNull()
        ->and($first->headers->get('Cache-Control'))->toContain('no-store');

    auth()->logout();
    ModuHelp::showWhen(fn () => true);

    $second = $this->get('/page');

    expect($second->getContent())->toContain(MARKER)
        ->and(identityMeta($second->getContent()))->toBe('')
        ->and($second->headers->get('ETag'))->toBe('"abc"')
        ->and($second->headers->get('Cache-Control'))->not->toContain('no-store');
});
