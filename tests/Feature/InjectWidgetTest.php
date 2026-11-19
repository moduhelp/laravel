<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use ModuHelp\Laravel\Facades\ModuHelp;
use ModuHelp\Laravel\Http\Middleware\InjectWidget;
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
    // The router empties the body of a HEAD response before middleware sees it,
    // so the rule is exercised by calling the middleware directly.
    $request = Request::create('/page', 'HEAD');
    $request->setUserResolver(fn () => signedIn());
    $original = new Response(PAGE, 200, ['Content-Type' => 'text/html', 'ETag' => '"abc"']);

    $result = app(InjectWidget::class)->handle($request, fn () => $original);

    expect($result)->toBe($original)
        ->and($result->getContent())->toBe(PAGE)
        ->and($result->headers->get('ETag'))->toBe('"abc"')
        ->and($result->headers->get('Cache-Control'))->not->toContain('no-store');
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
    $base = $response->baseResponse;

    expect(file_get_contents($base->getFile()->getPathname()))->toBe(PAGE)
        ->and($base)->toBeInstanceOf(BinaryFileResponse::class)
        ->and($base->headers->get('Content-Disposition'))->toContain('attachment')->toContain('page.html')
        ->and($base->headers->get('Cache-Control'))->not->toContain('no-store')
        ->and($base->headers->get('Content-Length'))->toBe((string) strlen(PAGE));

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

it('skips a string body marked as an attachment', function (string $disposition) {
    pageRoute(fn () => response(PAGE, 200, [
        'Content-Disposition' => $disposition,
        'Content-Length' => (string) strlen(PAGE),
        'ETag' => '"abc"',
    ]));

    $response = $this->get('/page');

    expect($response->getContent())->toBe(PAGE)
        ->and($response->headers->get('Content-Disposition'))->toBe($disposition)
        ->and($response->headers->get('Content-Length'))->toBe((string) strlen(PAGE))
        ->and($response->headers->get('ETag'))->toBe('"abc"')
        ->and($response->headers->get('Cache-Control'))->not->toContain('no-store');
})->with(['attachment; filename="p.html"', 'ATTACHMENT', 'Attachment;filename=p.html']);

it('still injects an inline content disposition', function () {
    pageRoute(fn () => response(PAGE, 200, ['Content-Disposition' => 'inline']));

    expect($this->get('/page')->getContent())->toContain(MARKER);
});

it('does not inject twice when the marker is single quoted, unquoted or upper case', function (string $meta) {
    $page = '<html><head>'.$meta.'</head><body></body></html>';
    pageRoute(fn () => response($page));

    expect($this->get('/page')->getContent())->toBe($page);
})->with([
    "<meta name='moduhelp-widget' content='x'>",
    '<meta name=moduhelp-widget content=x>',
    '<META NAME="MODUHELP-WIDGET" CONTENT="x">',
    '<meta content="x" name = "moduhelp-widget">',
]);

it('does not mistake the bootstrap selector text for the marker', function () {
    $page = '<html><head><script>d.querySelector(\'meta[name="moduhelp-widget"]\')</script></head><body></body></html>';
    pageRoute(fn () => response($page));

    expect($this->get('/page')->getContent())->toContain(MARKER);
});

it('injects before the real </head> when a script in the head contains the text', function () {
    $page = "<html><head><script>var a='</head>'</script><title>x</title></head><body><p></head></p></body></html>";
    pageRoute(fn () => response($page));

    $body = $this->get('/page')->getContent();

    expect(substr_count($body, MARKER))->toBe(1)
        ->and($body)->toStartWith("<html><head><script>var a='</head>'</script><title>x</title><meta name=\"moduhelp-widget\"")
        ->and($body)->toEndWith('</head><body><p></head></p></body></html>');
});

it('does not inject when </head> only appears after the body starts', function () {
    $page = '<html><body><p></head></p></body></html>';
    pageRoute(fn () => response($page));

    expect($this->get('/page')->getContent())->toBe($page);
});

it('skips the livewire-* except entry used by Livewire 4', function () {
    pageRoute(uri: 'livewire-abc123/preview-file/x');

    expect($this->get('/livewire-abc123/preview-file/x')->getContent())->toBe(PAGE);
});

it('hardens a component page that is publicly cacheable and injects nothing extra', function () {
    pageRoute(fn () => response(
        Blade::render('<html><head><x-moduhelp::widget /></head><body>hi</body></html>'),
        200,
        ['Cache-Control' => 'public, max-age=600', 'ETag' => '"abc"', 'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT'],
    ));

    $response = $this->get('/page');
    $cache = $response->headers->get('Cache-Control');

    expect(substr_count($response->getContent(), MARKER))->toBe(1)
        ->and($cache)->toContain('private')->toContain('no-store')->not->toContain('public')->not->toContain('max-age')
        ->and($response->headers->has('ETag'))->toBeFalse()
        ->and($response->headers->has('Last-Modified'))->toBeFalse();
});

it('leaves headers of an anonymous component page alone', function () {
    config()->set('moduhelp.identity_secret', null);
    pageRoute(fn () => response(
        Blade::render('<html><head><x-moduhelp::widget /></head><body>hi</body></html>'),
        200,
        ['Cache-Control' => 'public, max-age=600', 'ETag' => '"abc"'],
    ));

    $response = $this->get('/page');

    expect(substr_count($response->getContent(), MARKER))->toBe(1)
        ->and($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=600')
        ->and($response->headers->get('ETag'))->toBe('"abc"');
});

it('keeps Content-Length when the middleware only hardened a component page', function () {
    $html = Blade::render('<html><head><x-moduhelp::widget /></head><body>hi</body></html>');
    pageRoute(fn () => response($html, 200, ['Content-Length' => (string) strlen($html)]));

    $response = $this->get('/page');

    expect($response->headers->get('Content-Length'))->toBe((string) strlen($html));
});

it('gives each user in the same process their own token', function () {
    pageRoute();

    $a = $this->get('/page')->getContent();

    $this->actingAs(new TestUser(['id' => 9, 'email' => 'bob@example.com', 'name' => 'Bob']));
    $b = $this->get('/page')->getContent();

    $emailOf = fn (string $html) => json_decode(base64_decode(strtr(explode('.', identityMeta($html))[0], '-_', '+/')), true)['email'];

    expect($emailOf($a))->toBe('ana@example.com')
        ->and($emailOf($b))->toBe('bob@example.com');
});

it('still injects when MODUHELP_INJECT is set but blank', function () {
    config()->set('moduhelp.inject', '');
    pageRoute();

    expect(substr_count($this->get('/page')->getContent(), MARKER))->toBe(1);
});
