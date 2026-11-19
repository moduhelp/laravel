<?php

use Illuminate\Http\Request;
use ModuHelp\Laravel\Facades\ModuHelp;
use ModuHelp\Laravel\Tests\Fixtures\TestUser;

it('is disabled without a widget key', function () {
    config()->set('moduhelp.widget_key', null);

    expect(ModuHelp::enabled())->toBeFalse();
});

it('is enabled with a widget key', function () {
    expect(ModuHelp::enabled())->toBeTrue();
});

it('trims the trailing slash from the origin', function () {
    config()->set('moduhelp.origin', 'https://x.test/');

    expect(ModuHelp::origin())->toBe('https://x.test');
});

it('defaults the origin', function () {
    expect(ModuHelp::origin())->toBe('https://app.moduhelp.com');
});

it('clamps the ttl', function ($in, $out) {
    config()->set('moduhelp.token_ttl', $in);

    expect(ModuHelp::ttl())->toBe($out);
})->with([[0, 60], [-5, 60], ['600', 600], [999999, 86400], [null, 43200]]);

it('binds the manager as a singleton with an alias', function () {
    expect(app('moduhelp'))->toBe(app(\ModuHelp\Laravel\ModuHelp::class));
});

it('publishes the config', function () {
    @unlink(config_path('moduhelp.php'));

    $this->artisan('vendor:publish', ['--tag' => 'moduhelp-config'])->assertSuccessful();

    expect(file_exists(config_path('moduhelp.php')))->toBeTrue();

    @unlink(config_path('moduhelp.php'));
});

it('falls back to the default origin unless it is a plain absolute http(s) url', function (mixed $value, string $expected) {
    config()->set('moduhelp.origin', $value);

    expect(ModuHelp::origin())->toBe($expected);
})->with([
    ['javascript:alert(1)', 'https://app.moduhelp.com'],
    ['//evil.test', 'https://app.moduhelp.com'],
    ['ftp://x.test', 'https://app.moduhelp.com'],
    ['https://x.test" onload="1', 'https://app.moduhelp.com'],
    ["https://x.test'", 'https://app.moduhelp.com'],
    ['https://x.test/<b>', 'https://app.moduhelp.com'],
    ['https://x. test', 'https://app.moduhelp.com'],
    ['https://', 'https://app.moduhelp.com'],
    [['x'], 'https://app.moduhelp.com'],
    ['http://127.0.0.1:8000/', 'http://127.0.0.1:8000'],
    ['  https://x.test/  ', 'https://x.test'],
]);

it('uses the trimmed widget key everywhere', function () {
    config()->set('moduhelp.widget_key', ' wk_test ');
    $user = new TestUser(['id' => 7, 'email' => 'ana@example.com', 'name' => 'Ana']);
    $this->actingAs($user);

    $token = ModuHelp::token($user);
    $claims = json_decode(base64_decode(strtr(explode('.', $token)[0], '-_', '+/')), true);
    $html = ModuHelp::render(Request::create('/'));

    expect(ModuHelp::enabled())->toBeTrue()
        ->and($claims['k'])->toBe('wk_test')
        ->and($html)->toContain('content="wk_test"');
});
