# moduhelp/laravel

Adds the ModuHelp support widget to the HTML pages of a Laravel app and, for signed-in users, passes their name and email to the widget in a signed token so the contact form arrives prefilled and marked as verified. Install it, set a few env lines, and the widget appears. If no widget key is configured, the package does nothing at all.

## Requirements

- PHP 8.2 or later (Laravel 13 itself needs PHP 8.3)
- Laravel 11, 12 or 13
- A ModuHelp workspace with the widget enabled, its widget key, and an identity secret
- Your site's origin added to the widget's allowed sites in ModuHelp

## Install

```bash
composer require moduhelp/laravel
```

Add the credentials to `.env`:

```dotenv
MODUHELP_WIDGET_KEY=
MODUHELP_IDENTITY_SECRET=
```

The service provider and the `ModuHelp` facade are registered by package discovery. To change the defaults below, publish the config file:

```bash
php artisan vendor:publish --tag=moduhelp-config
```

## Configuration

| Env | Config key | Default | Notes |
|---|---|---|---|
| `MODUHELP_WIDGET_KEY` | `widget_key` | none | Public value. Blank means the package is fully inert. |
| `MODUHELP_IDENTITY_SECRET` | `identity_secret` | none | Signs identity tokens. Never rendered. Blank means the widget is shown anonymously. |
| `MODUHELP_ORIGIN` | `origin` | `https://app.moduhelp.com` | Where the widget is served from. Must be an absolute `http://` or `https://` URL; a blank or invalid value falls back to the default. A trailing slash is trimmed. |
| `MODUHELP_INJECT` | `inject` | `true` | Turns automatic injection on or off. Off stops injection only; the cache protection for pages that carry a token stays on (see Caching). Only `false`, `off`, `no` or `0` turn it off; unset or blank keeps it on. |
| `MODUHELP_TOKEN_TTL` | `token_ttl` | `43200` | Token lifetime in seconds. Clamped to 60 through 86400; a non-numeric value falls back to the default. |
| `MODUHELP_GUARD` | `guard` | default guard | Auth guard used to find the user. |
| none | `except` | `horizon*`, `telescope*`, `pulse*`, `_debugbar*`, `livewire/*`, `livewire-*` | Request path patterns (`Request::is`) that never get the widget. |

## How automatic injection decides

The package appends a middleware to the `web` group. It inserts the widget before the last `</head>` that comes before the first `<body` (a `</head>` inside a head script is skipped), and only when `MODUHELP_INJECT` is on and all of these hold:

- The request is a `GET`, is not an AJAX request, carries no `X-Livewire` header, its path does not match `except`, and `ModuHelp::skip()` was not called.
- The response is a plain, non-streamed response with status 200, a `Content-Type` starting with `text/html`, no `Content-Encoding`, and no `Content-Disposition: attachment`.
- The response is not publicly cacheable: its `Cache-Control` has neither `public` nor `s-maxage`.
- The body has a `</head>` to insert before and does not already contain the widget's `<meta name="moduhelp-widget">` (for example because you placed the component yourself).
- The `showWhen` check passes (see below).

It never touches Livewire update requests, JSON, redirects, downloads, streamed responses, error pages, or non-GET requests. Requests made by `wire:navigate` are injected, because their response becomes the next page. If anything inside the package throws, the exception is reported and the original response is returned unchanged.

The middleware is registered when the HTTP kernel is first resolved, so console and queue processes never build it. If the app has no `web` middleware group, nothing is registered and nothing breaks.

## Who sees the widget

By default, signed-in users only, resolved on the configured guard. Guests, including the login page, get nothing.

`showWhen` replaces that default. It receives the request and the user (or `null`) and returns a boolean. Because it replaces the default, a callback that wants to keep the signed-in requirement must check `$user` itself. Guests who pass get an anonymous widget with no token. `showWhen` never overrides the response rules above.

Register it in a service provider's `boot` method:

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use ModuHelp\Laravel\Facades\ModuHelp;

public function boot(): void
{
    // Signed-in users, except while an admin is impersonating someone.
    ModuHelp::showWhen(fn (Request $request, ?Authenticatable $user): bool => $user !== null
        && ! $request->session()->has('impersonate'));
}
```

To leave the widget off a single page (a print view, an embedded frame), call `ModuHelp::skip()` while handling that request:

```php
public function print(Invoice $invoice)
{
    ModuHelp::skip();

    return view('invoices.print', compact('invoice'));
}
```

For whole groups of routes, add path patterns to `except` instead.

## Identity

For a signed-in user, the package signs an identity token with `MODUHELP_IDENTITY_SECRET`. The token carries the widget key, an expiry, and by default `$user->email`, `$user->name` and `$user->getAuthIdentifier()`.

To change or add claims, register `identifyUsing` in a service provider's `boot` method. Return any of `email`, `name`, `uid` and `attrs`; what you return is merged over the defaults.

```php
ModuHelp::identifyUsing(fn (Authenticatable $user): array => [
    'name' => $user->full_name,
    'attrs' => [
        'plan' => $user->plan,
        'account_id' => $user->account_id,
        'is_admin' => $user->isAdmin(),
    ],
]);
```

ModuHelp refuses tokens that break its limits, so the package normalises every claim and drops what does not fit:

- `email` is required, must be valid and ASCII only, at most 254 characters. It is lowercased.
- `name` is cut to 100 characters. `uid` is an integer or a string cut to 191 characters.
- `attrs`: at most 10 entries. Keys are lowercase letters, digits and underscores, 1 to 40 characters, and not all digits. Values must be scalars. Strings are at most 200 characters. Entries that break a rule are dropped, and `null` values are dropped.
- The whole token is at most 4096 bytes. If it is longer, `attrs` are dropped; if it is still too long, there is no token.

When the secret is blank, there is no signed-in user, or the email is unusable, the widget is shown anonymously. The package never throws into your app because of bad identity data.

`ModuHelp::token(?Authenticatable $user = null)` returns the token string or `null`, for the manual setups below.

## Content Security Policy

The package does not send or change any CSP header. If your app sends one, the widget needs three things from it.

Origin: `ModuHelp::origin()` must be allowed in `script-src`, `connect-src` and `frame-src`.

Nonce: the package's inline bootstrap script is blocked unless it carries a nonce your policy accepts (or your policy already allows inline scripts). By default the nonce comes from `Vite::cspNonce()`, which returns `null` unless your app calls `Vite::useCspNonce()`, so without that call the script has no nonce. If your nonce comes from somewhere else, register it:

```php
ModuHelp::nonceUsing(fn (Request $request): ?string => $request->attributes->get('csp_nonce'));
```

The example below is a sketch of your own policy, not a replacement for it. It builds your complete policy in one place, with the nonce and the ModuHelp origin added only on pages where the widget was rendered. Keep your own directives and values; the ones shown are placeholders.

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use ModuHelp\Laravel\Facades\ModuHelp;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Must run before the view renders so Vite and the widget share one nonce.
        Vite::useCspNonce();

        $response = $next($request);

        $nonce = Vite::cspNonce();
        $origin = ModuHelp::renderedOn($request) ? ' '.ModuHelp::origin() : '';

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$origin}",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "connect-src 'self'{$origin}",
            "frame-src 'self'{$origin}",
        ]));

        return $response;
    }
}
```

The package middleware is appended to the end of the `web` group, so a middleware registered before it wraps it and sees the finished response. `renderedOn` is already set by then.

## Manual placement

Set `MODUHELP_INJECT=false` and put the component in your layout, in `<head>` (or anywhere in `<body>`; the bootstrap script always adds the widget loader to `<head>`):

```blade
<head>
    ...
    <x-moduhelp::widget />
</head>
```

The component applies the same checks as automatic injection for who sees the widget (enabled, `showWhen`, `skip()`), but not the response rules: you chose the page. If both the component and the middleware would render, only one widget appears.

Trigger modes: the floating and custom modes are supported. Inline trigger mode is not supported in 0.1. The widget loader places the inline button right after its own script element, and this package always adds that script to `<head>`, wherever the component sits. A page that needs inline mode should use the plain widget snippet from the ModuHelp settings page in its body instead, and call `ModuHelp::skip()` (or match an `except` pattern) on that page so it does not get a second widget.

## Buttons

Any element with a `data-moduhelp-open` attribute opens the widget:

```html
<button type="button" data-moduhelp-open>Contact support</button>
```

## Livewire and Turbo

`wire:navigate` and Turbo Drive are supported. The widget loads once, and on each navigation the identity is refreshed from the new page, so signing in or out is picked up.

## Caching

A page that carries an identity token is personal. Whenever a token was rendered on a request, whether by auto-injection or by the component, and the route is in the `web` group, the response leaves with `Cache-Control: private, no-store` (Symfony may print the directives as `no-store, private`) and without `ETag` and `Last-Modified`. This overrides a `public` or `s-maxage` the app set. `Content-Length` is dropped only when the package changed the body. A consequence: such pages are not kept by the browser back/forward cache and are not revalidated conditionally.

Auto-injection never touches a response marked `public` or `s-maxage`, so it does not add a token to a page a shared cache may store.

`MODUHELP_INJECT=false` turns off injection only. The cache protection for pages that carry a token stays on.

Two cases get no protection from the package, so never cache a page that contains the identity meta:

- A component placed on a route outside the `web` group.
- A page stored by a full-page response cache package that serves responses without running the middleware stack. Exclude signed-in pages from it.

## Inertia

Automatic identity is not supported for Inertia apps. Inertia visits are XHR requests that the package skips, so signing in or out inside the SPA would never update the widget.

A manual recipe: share the token as a prop, and hand it to the widget on every navigation.

```php
// app/Http/Middleware/HandleInertiaRequests.php
use Illuminate\Http\Request;
use ModuHelp\Laravel\Facades\ModuHelp;

public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'moduhelpToken' => fn () => ModuHelp::token(),
    ]);
}
```

```js
// resources/js/app.js
import { router } from '@inertiajs/core' // or the router exported by your Inertia adapter

router.on('navigate', (event) => {
    const token = event.detail.page.props.moduhelpToken

    window.ModuHelpWidget?.identify?.(token ? { token } : null)
})
```

Set `MODUHELP_INJECT=false` and place `<x-moduhelp::widget />` in your root Blade template so the widget itself loads on the first page.

## Security notes

- The identity secret is never rendered, logged or included in exceptions. Keep it in the environment, not in the repository.
- The widget key is public. It appears in page source by design.
- The token and the email are never logged.
- Tokens expire after `MODUHELP_TOKEN_TTL` seconds. Pages that carry one leave with `private, no-store`.

## Testing

```bash
composer install
vendor/bin/pest
vendor/bin/pint --test
```

## Licence

MIT. See `LICENSE`.
