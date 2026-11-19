<?php

use ModuHelp\Laravel\Facades\ModuHelp;

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
