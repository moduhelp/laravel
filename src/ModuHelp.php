<?php

namespace ModuHelp\Laravel;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;
use ModuHelp\Laravel\Identity\TokenMinter;
use Throwable;

/**
 * Holds configuration access and the three host closures only, never request,
 * user or token state, so it is safe to keep as a long-lived singleton
 * (Octane). Per-request state lives in request attributes.
 */
class ModuHelp
{
    private const MIN_TTL = 60;

    private const MAX_TTL = 86400;

    private const DEFAULT_TTL = 43200;

    private const DEFAULT_ORIGIN = 'https://app.moduhelp.com';

    private ?Closure $identify = null;

    private ?Closure $showWhen = null;

    private ?Closure $nonceUsing = null;

    public function __construct(private readonly Repository $config) {}

    /** @param Closure(Authenticatable): array<string, mixed> $fn */
    public function identifyUsing(Closure $fn): void
    {
        $this->identify = $fn;
    }

    /** @param Closure(Request, ?Authenticatable): bool $fn */
    public function showWhen(Closure $fn): void
    {
        $this->showWhen = $fn;
    }

    /** @param Closure(Request): ?string $fn */
    public function nonceUsing(Closure $fn): void
    {
        $this->nonceUsing = $fn;
    }

    public function skip(?Request $request = null): void
    {
        ($request ?? request())->attributes->set('moduhelp.skip', true);
    }

    public function user(): ?Authenticatable
    {
        try {
            $guard = $this->config->get('moduhelp.guard');
            $guard = is_string($guard) && $guard !== '' ? $guard : null;

            return Auth::guard($guard)->user();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function shouldShow(Request $request): bool
    {
        if (! $this->enabled() || $request->attributes->get('moduhelp.skip') === true) {
            return false;
        }

        try {
            $user = $this->user();

            return $this->showWhen !== null
                ? (bool) ($this->showWhen)($request, $user)
                : $user !== null;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function token(?Authenticatable $user = null): ?string
    {
        $widgetKey = $this->widgetKey();
        $secret = $this->config->get('moduhelp.identity_secret');

        if ($widgetKey === null || ! is_string($secret) || trim($secret) === '') {
            return null;
        }

        $user ??= $this->user();

        if ($user === null) {
            return null;
        }

        try {
            $claims = [
                'email' => $user->email ?? null,
                'name' => $user->name ?? null,
                'uid' => $user->getAuthIdentifier(),
            ];

            if ($this->identify !== null) {
                $custom = ($this->identify)($user);
                $claims = (is_array($custom) ? $custom : []) + $claims;
            }

            return (new TokenMinter($widgetKey, $secret, $this->ttl()))->mint($claims);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Never throws into the host: a failed render yields null and sets no attribute. */
    public function render(Request $request): ?string
    {
        if (! $this->shouldShow($request)) {
            return null;
        }

        try {
            $token = $this->token($this->user());

            $html = view('moduhelp::widget', [
                'key' => $this->widgetKey(),
                'origin' => $this->origin(),
                'token' => $token,
                'nonce' => $this->nonce($request),
            ])->render();
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $request->attributes->set('moduhelp.rendered', true);
        $request->attributes->set('moduhelp.identified', $token !== null);

        return $html;
    }

    public function nonce(Request $request): ?string
    {
        try {
            $nonce = $this->nonceUsing !== null
                ? ($this->nonceUsing)($request)
                : Vite::cspNonce();

            return is_string($nonce) && $nonce !== '' ? $nonce : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function renderedOn(Request $request): bool
    {
        return $request->attributes->get('moduhelp.rendered') === true;
    }

    public function identifiedOn(Request $request): bool
    {
        return $request->attributes->get('moduhelp.identified') === true;
    }

    public function enabled(): bool
    {
        return $this->widgetKey() !== null;
    }

    /** The one trimmed reading of the key, shared by enabled(), the token claim and the markup. */
    private function widgetKey(): ?string
    {
        $key = $this->config->get('moduhelp.widget_key');

        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        return trim($key);
    }

    public function origin(): string
    {
        $origin = $this->config->get('moduhelp.origin');
        $origin = is_string($origin) ? trim($origin) : '';

        // Absolute http(s) with a host, and nothing that could break out of an attribute.
        if (preg_match('#^https?://[^\\s"\'`<>/\\\\?\\#][^\\s"\'`<>\\\\]*$#i', $origin) !== 1) {
            return self::DEFAULT_ORIGIN;
        }

        return rtrim($origin, '/');
    }

    /**
     * Whether auto-injection is on. Unset, blank or unrecognised values mean the
     * default (on): a bare `MODUHELP_INJECT=` line in .env reaches config as an
     * empty string, which must not silently turn the widget off.
     */
    public function injects(): bool
    {
        $value = $this->config->get('moduhelp.inject');

        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function ttl(): int
    {
        $ttl = $this->config->get('moduhelp.token_ttl');

        $ttl = is_numeric($ttl) ? (int) $ttl : self::DEFAULT_TTL;

        return max(self::MIN_TTL, min(self::MAX_TTL, $ttl));
    }
}
