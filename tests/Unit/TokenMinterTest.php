<?php

use Illuminate\Support\Carbon;
use ModuHelp\Laravel\Identity\TokenMinter;

beforeEach(function () {
    Carbon::setTestNow(Carbon::createFromTimestamp(1767225600 - 43200));
});

afterEach(function () {
    Carbon::setTestNow();
});

function minter(): TokenMinter
{
    return new TokenMinter('wk_test', 'mis_test_secret', 43200);
}

function decoded(?string $token): array
{
    [$payload] = explode('.', (string) $token, 2);

    return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
}

function mintWith(array $extra): ?string
{
    return minter()->mint(['email' => 'ana@example.com'] + $extra);
}

test('golden vector', function () {
    expect(minter()->mint(['email' => 'Ana@Example.com ', 'name' => 'Ana', 'uid' => 7]))
        ->toBe('eyJrIjoid2tfdGVzdCIsImVtYWlsIjoiYW5hQGV4YW1wbGUuY29tIiwibmFtZSI6IkFuYSIsInVpZCI6NywiZXhwIjoxNzY3MjI1NjAwfQ.27e3ca6eff5454f9a6ad6dee2518f0c034620146fca15b2f1685bb757d8e9d14');
});

test('returns null for a non-ascii email', function () {
    expect(minter()->mint(['email' => 'jose@exämple.com']))->toBeNull();
});

test('returns null for an invalid email', function () {
    expect(minter()->mint(['email' => 'not-an-email']))->toBeNull();
});

test('returns null for an email over 254 chars', function () {
    expect(minter()->mint(['email' => str_repeat('a', 250).'@example.com']))->toBeNull();
});

test('returns null for a missing email', function () {
    expect(minter()->mint(['name' => 'Ana']))->toBeNull();
});

test('cuts the name to 100 chars and to one line', function () {
    expect(decoded(mintWith(['name' => "Ana\nMaria"]))['name'])->toBe('Ana Maria');

    $name = decoded(mintWith(['name' => str_repeat('é', 150)]))['name'];
    expect(mb_strlen($name))->toBe(100);
});

test('omits a non-scalar name and a blank name', function () {
    expect(decoded(mintWith(['name' => ['x']])))->not->toHaveKey('name');
    expect(decoded(mintWith(['name' => " \n "])))->not->toHaveKey('name');
});

test('casts a scalar name', function () {
    expect(decoded(mintWith(['name' => 123]))['name'])->toBe('123');
});

test('keeps an int uid, cuts a string uid to 191, omits other uid types', function () {
    expect(decoded(mintWith(['uid' => 42]))['uid'])->toBe(42);
    expect(mb_strlen(decoded(mintWith(['uid' => str_repeat('u', 300)]))['uid']))->toBe(191);
    expect(decoded(mintWith(['uid' => ['a']])))->not->toHaveKey('uid');
    expect(decoded(mintWith(['uid' => 1.5])))->not->toHaveKey('uid');
    expect(decoded(mintWith(['uid' => ''])))->not->toHaveKey('uid');
});

test('filters attrs', function () {
    $attrs = [
        'plan' => 'pro', '123' => 'x', 'Bad-Key' => 'x', 'n' => null,
        'arr' => [1], 'long' => str_repeat('é', 201), 'ok' => true,
    ];

    expect(decoded(mintWith(['attrs' => $attrs]))['attrs'])->toBe(['plan' => 'pro', 'ok' => true]);
});

test('keeps the first 10 of 12 valid attrs', function () {
    $attrs = [];
    foreach (range(1, 12) as $i) {
        $attrs["k$i"] = $i;
    }

    expect(array_keys(decoded(mintWith(['attrs' => $attrs]))['attrs']))
        ->toBe(array_map(fn ($i) => "k$i", range(1, 10)));
});

test('omits attrs when empty', function () {
    expect(decoded(mintWith(['attrs' => []])))->not->toHaveKey('attrs');
    expect(decoded(mintWith(['attrs' => ['a', 'b']])))->not->toHaveKey('attrs');
    expect(decoded(mintWith(['attrs' => 'x'])))->not->toHaveKey('attrs');
});

test('drops attrs when the token would exceed 4096', function () {
    $attrs = [];
    foreach (range(1, 10) as $i) {
        $attrs["k$i"] = str_repeat('ü', 200);
    }

    $token = mintWith(['attrs' => $attrs]);

    expect($token)->not->toBeNull()
        ->and(strlen($token))->toBeLessThanOrEqual(4096)
        ->and(decoded($token))->not->toHaveKey('attrs');
});

test('multibyte name with emoji and 10 long multibyte attrs still yields a token', function () {
    $name = 'José Ñandú 日本語 😀';
    $attrs = [];
    foreach (range(1, 10) as $i) {
        $attrs["k$i"] = str_repeat('日', 200);
    }

    $token = mintWith(['name' => $name, 'attrs' => $attrs]);

    expect($token)->not->toBeNull()
        ->and(strlen($token))->toBeLessThanOrEqual(4096)
        ->and(decoded($token)['name'])->toBe($name)
        ->and(decoded($token))->not->toHaveKey('attrs');
});

test('returns null on invalid utf-8 and on NAN', function () {
    expect(mintWith(['name' => "\xB1\x31"]))->toBeNull();
    expect(mintWith(['attrs' => ['x' => NAN]]))->toBeNull();
});

test('exp is now plus ttl and the token has the contract shape', function () {
    $token = mintWith(['name' => 'Ana']);

    expect(decoded($token)['exp'])->toBe(1767225600)
        ->and(explode('.', $token)[0])->not->toContain('=')
        ->and($token)->toMatch('/^[A-Za-z0-9_-]+\.[0-9a-f]{64}$/');
});
