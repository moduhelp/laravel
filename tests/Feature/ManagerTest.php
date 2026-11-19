<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Vite;
use ModuHelp\Laravel\Facades\ModuHelp;
use ModuHelp\Laravel\Tests\Fixtures\TestUser;

function managerUser(string $email = 'ana@example.com'): TestUser
{
    return new TestUser(['id' => 7, 'email' => $email, 'name' => 'Ana']);
}

function decodeClaims(string $token): array
{
    [$segment] = explode('.', $token);

    return json_decode(base64_decode(strtr($segment, '-_', '+/')), true);
}

it('shows only to signed-in users by default', function () {
    $request = Request::create('/');

    expect(ModuHelp::shouldShow($request))->toBeFalse();

    $this->actingAs(managerUser());

    expect(ModuHelp::shouldShow($request))->toBeTrue();
});

it('does not show when the widget is not configured', function () {
    config()->set('moduhelp.widget_key', '');
    $this->actingAs(managerUser());

    expect(ModuHelp::shouldShow(Request::create('/')))->toBeFalse();
});

it('showWhen replaces the default and receives the user', function () {
    $received = null;
    ModuHelp::showWhen(function (Request $request, $user) use (&$received) {
        $received = $user;

        return true;
    });

    expect(ModuHelp::shouldShow(Request::create('/')))->toBeTrue()
        ->and($received)->toBeNull();

    $user = managerUser();
    $this->actingAs($user);
    ModuHelp::shouldShow(Request::create('/'));

    expect($received)->toBe($user);
});

it('skip hides the widget for that request only', function () {
    $this->actingAs(managerUser());
    $first = Request::create('/');
    $second = Request::create('/');

    ModuHelp::skip($first);

    expect(ModuHelp::shouldShow($first))->toBeFalse()
        ->and(ModuHelp::shouldShow($second))->toBeTrue();
});

it('skip uses the current request when none is given', function () {
    $this->actingAs(managerUser());
    $current = Request::create('/current');
    app()->instance('request', $current);

    ModuHelp::skip();

    expect($current->attributes->get('moduhelp.skip'))->toBeTrue();
});

it('a throwing showWhen hides the widget', function () {
    Exceptions::fake();
    $this->actingAs(managerUser());
    ModuHelp::showWhen(fn () => throw new RuntimeException('boom'));

    expect(ModuHelp::shouldShow(Request::create('/')))->toBeFalse();
    Exceptions::assertReported(RuntimeException::class);
});

it('a throwing identifyUsing gives an anonymous widget', function () {
    Exceptions::fake();
    $user = managerUser();
    ModuHelp::identifyUsing(fn () => throw new RuntimeException('boom'));

    expect(ModuHelp::token($user))->toBeNull();
    Exceptions::assertReported(RuntimeException::class);
});

it('token is null without a secret', function () {
    config()->set('moduhelp.identity_secret', '');

    expect(ModuHelp::token(managerUser()))->toBeNull();
});

it('token is null without a widget key', function () {
    config()->set('moduhelp.widget_key', '');

    expect(ModuHelp::token(managerUser()))->toBeNull();
});

it('token is null without a user', function () {
    expect(ModuHelp::token())->toBeNull();
});

it('token falls back to the current user', function () {
    $this->actingAs(managerUser());

    $claims = decodeClaims(ModuHelp::token());

    expect($claims['email'])->toBe('ana@example.com')
        ->and($claims['name'])->toBe('Ana')
        ->and($claims['uid'])->toBe(7)
        ->and($claims['k'])->toBe('wk_test');
});

it('token uses the clamped ttl', function () {
    config()->set('moduhelp.token_ttl', 5);

    $claims = decodeClaims(ModuHelp::token(managerUser()));

    expect($claims['exp'] - time())->toBeBetween(58, 62);
});

it('identifyUsing merges over defaults', function () {
    ModuHelp::identifyUsing(fn () => ['attrs' => ['plan' => 'pro']]);

    $claims = decodeClaims(ModuHelp::token(managerUser()));

    expect($claims['email'])->toBe('ana@example.com')
        ->and($claims['attrs'])->toBe(['plan' => 'pro']);
});

it('identifyUsing can override defaults', function () {
    ModuHelp::identifyUsing(fn () => ['email' => 'other@example.com', 'uid' => 'u-1']);

    $claims = decodeClaims(ModuHelp::token(managerUser()));

    expect($claims['email'])->toBe('other@example.com')
        ->and($claims['uid'])->toBe('u-1');
});

it('a non-array identifyUsing result falls back to the defaults', function () {
    ModuHelp::identifyUsing(fn () => 'nope');

    expect(decodeClaims(ModuHelp::token(managerUser()))['email'])->toBe('ana@example.com');
});

it('a user without email or name does not throw', function () {
    $user = new GenericUser(['id' => 1]);

    expect(ModuHelp::token($user))->toBeNull();
});

it('carries no token after logout in the same process', function () {
    $this->actingAs(managerUser());

    expect(ModuHelp::token())->not->toBeNull();

    Auth::logout();

    expect(ModuHelp::token(ModuHelp::user()))->toBeNull()
        ->and(ModuHelp::token())->toBeNull();
});

it('user never throws on a misconfigured guard', function () {
    Exceptions::fake();
    config()->set('moduhelp.guard', 'does-not-exist');

    expect(ModuHelp::user())->toBeNull();
    Exceptions::assertReported(InvalidArgumentException::class);
});

it('user reads the configured guard', function () {
    config()->set('auth.guards.alt', ['driver' => 'session', 'provider' => 'users']);
    config()->set('moduhelp.guard', 'alt');
    $user = managerUser();
    Auth::guard('alt')->setUser($user);

    expect(ModuHelp::user())->toBe($user);
});

it('nonce defaults to the vite csp nonce', function () {
    Vite::useCspNonce('abc123');

    expect(ModuHelp::nonce(Request::create('/')))->toBe('abc123');
});

it('nonceUsing replaces the default and receives the request', function () {
    $request = Request::create('/');
    $seen = null;
    ModuHelp::nonceUsing(function (Request $r) use (&$seen) {
        $seen = $r;

        return 'custom';
    });

    expect(ModuHelp::nonce($request))->toBe('custom')
        ->and($seen)->toBe($request);
});

it('nonce is null for empty, non-string or throwing results', function () {
    $request = Request::create('/');

    ModuHelp::nonceUsing(fn () => '');
    expect(ModuHelp::nonce($request))->toBeNull();

    ModuHelp::nonceUsing(fn () => 42);
    expect(ModuHelp::nonce($request))->toBeNull();

    ModuHelp::nonceUsing(fn () => throw new RuntimeException('boom'));
    expect(ModuHelp::nonce($request))->toBeNull();
});

it('reads the rendered and identified request attributes', function () {
    $request = Request::create('/');

    expect(ModuHelp::renderedOn($request))->toBeFalse()
        ->and(ModuHelp::identifiedOn($request))->toBeFalse();

    $request->attributes->set('moduhelp.rendered', true);
    $request->attributes->set('moduhelp.identified', true);

    expect(ModuHelp::renderedOn($request))->toBeTrue()
        ->and(ModuHelp::identifiedOn($request))->toBeTrue();
});
