<?php

namespace ModuHelp\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ModuHelp\Laravel\ModuHelp;
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
            if (! $this->requestQualifies($request) || ! $this->responseQualifies($response)) {
                return $response;
            }

            $content = $response->getContent();
            $position = stripos($content, '</head>');

            if ($position === false || str_contains($content, 'name="moduhelp-widget"')) {
                return $response;
            }

            $html = $this->moduHelp->render($request);

            if ($html === null) {
                return $response;
            }

            // Compute everything first, mutate last, so a failure leaves the original intact.
            $injected = substr($content, 0, $position).$html.substr($content, $position);

            if ($this->moduHelp->identifiedOn($request)) {
                // The page now carries a per-user token: it must never be stored or revalidated.
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->remove('ETag');
                $response->headers->remove('Last-Modified');
            }

            // The body length changed whether or not a token was added.
            $response->headers->remove('Content-Length');
            $response->setContent($injected);
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
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
