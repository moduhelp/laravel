<?php

namespace ModuHelp\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ModuHelp\Laravel\ModuHelp;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Injects the widget into full HTML pages only. Every rule below fails closed:
 * when in doubt the response is returned exactly as the app produced it.
 */
class InjectWidget
{
    public function __construct(private readonly ModuHelp $moduHelp) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        try {
            $bodyChanged = $this->inject($request, $response);

            // Independent of the injection rules: a token was rendered on this
            // request (here or by the component), so the page is personal.
            if ($this->moduHelp->identifiedOn($request) && $response instanceof SymfonyResponse) {
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->remove('ETag');
                $response->headers->remove('Last-Modified');

                if ($bodyChanged) {
                    $response->headers->remove('Content-Length');
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }

    /** Returns true when the body of the response was replaced. */
    private function inject(Request $request, mixed $response): bool
    {
        // Read per request: the middleware is registered whenever the package is
        // enabled, because it also hardens pages the component identified.
        if (! $this->moduHelp->injects()) {
            return false;
        }

        if (! $this->requestQualifies($request) || ! $this->responseQualifies($response)) {
            return false;
        }

        $content = $response->getContent();
        $position = $this->insertionPoint($content);

        if ($position === null || $this->hasMarker($content)) {
            return false;
        }

        $html = $this->moduHelp->render($request);

        if ($html === null) {
            return false;
        }

        // Compute first, mutate last, so a failure leaves the original intact.
        $injected = substr($content, 0, $position).$html.substr($content, $position);

        $response->headers->remove('Content-Length');
        $response->setContent($injected);

        return true;
    }

    /**
     * Before the last </head> that precedes the first <body, so a "</head>"
     * inside a head script is skipped; without a <body, the first </head>.
     */
    private function insertionPoint(string $content): ?int
    {
        if (preg_match('/<body\b/i', $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
            $first = stripos($content, '</head>');

            return $first === false ? null : $first;
        }

        $last = strripos(substr($content, 0, $match[0][1]), '</head>');

        return $last === false ? null : $last;
    }

    private function hasMarker(string $content): bool
    {
        return preg_match('/<meta\b[^>]*\bname\s*=\s*["\']?moduhelp-widget\b/i', $content) === 1;
    }

    private function requestQualifies(Request $request): bool
    {
        return $request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->headers->has('X-Livewire')
            && ! $request->is($this->exceptPatterns())
            && $request->attributes->get('moduhelp.skip') !== true;
    }

    private function responseQualifies(mixed $response): bool
    {
        if (! $response instanceof Response || $response->getStatusCode() !== 200) {
            return false;
        }

        $type = strtolower(trim((string) $response->headers->get('Content-Type')));

        if (! str_starts_with($type, 'text/html') || $response->headers->has('Content-Encoding')) {
            return false;
        }

        if (stripos((string) $response->headers->get('Content-Disposition'), 'attachment') !== false) {
            return false;
        }

        $cacheControl = (string) $response->headers->get('Cache-Control');

        if (preg_match('/(?<![\w-])(public|s-maxage)(?![\w-])/i', $cacheControl) === 1) {
            return false;
        }

        $content = $response->getContent();

        return is_string($content) && $content !== '';
    }

    /** @return list<string> */
    private function exceptPatterns(): array
    {
        $except = config('moduhelp.except', []);

        return is_array($except) ? array_values(array_filter($except, 'is_string')) : [];
    }
}
