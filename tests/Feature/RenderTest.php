<?php

use Illuminate\Http\Request;
use ModuHelp\Laravel\Facades\ModuHelp;
use ModuHelp\Laravel\Tests\Fixtures\TestUser;

function renderUser(): TestUser
{
    return new TestUser(['id' => 7, 'email' => 'ana@example.com', 'name' => 'Ana']);
}

it('renders both metas and the bootstrap', function () {
    $this->actingAs(renderUser());
    $this->freezeTime();

    $html = ModuHelp::render(Request::create('/'));
    $token = ModuHelp::token(renderUser());

    expect($html)
        ->toContain('<meta name="moduhelp-widget" content="wk_test" data-origin="https://app.moduhelp.com">')
        ->toContain('<meta name="moduhelp-identity" content="'.$token.'">')
        ->toContain("+'/widget/v1.js'")
        ->toContain("c.getAttribute('data-origin')");
});

it('renders an empty identity meta without a secret', function () {
    config()->set('moduhelp.identity_secret', '');
    $this->actingAs(renderUser());

    $request = Request::create('/');
    $html = ModuHelp::render($request);

    expect($html)->toContain('<meta name="moduhelp-identity" content="">')
        ->and(ModuHelp::renderedOn($request))->toBeTrue()
        ->and(ModuHelp::identifiedOn($request))->toBeFalse();
});

it('escapes values', function () {
    config()->set('moduhelp.widget_key', 'wk"><script>');
    $this->actingAs(renderUser());

    $html = ModuHelp::render(Request::create('/'));

    expect($html)
        ->toContain('content="wk&quot;&gt;&lt;script&gt;"')
        ->not->toContain('wk"><script>');
});

it('adds the nonce when nonceUsing returns one', function () {
    $this->actingAs(renderUser());

    ModuHelp::nonceUsing(fn () => 'abc');
    expect(ModuHelp::render(Request::create('/')))->toContain('nonce="abc"');

    ModuHelp::nonceUsing(fn () => null);
    expect(ModuHelp::render(Request::create('/')))->not->toContain('nonce="');
});

it('returns null and sets no attribute when not shown', function () {
    $request = Request::create('/');

    expect(ModuHelp::render($request))->toBeNull()
        ->and(ModuHelp::renderedOn($request))->toBeFalse()
        ->and(ModuHelp::identifiedOn($request))->toBeFalse();
});

it('sets rendered and identified attributes when shown', function () {
    $this->actingAs(renderUser());

    $request = Request::create('/');
    ModuHelp::render($request);

    expect(ModuHelp::renderedOn($request))->toBeTrue()
        ->and(ModuHelp::identifiedOn($request))->toBeTrue();
});

it('never throws and sets no attributes when the view fails', function () {
    $this->actingAs(renderUser());
    $this->app['view']->replaceNamespace('moduhelp', __DIR__.'/../Fixtures/missing-views');

    $request = Request::create('/');

    expect(ModuHelp::render($request))->toBeNull()
        ->and(ModuHelp::renderedOn($request))->toBeFalse()
        ->and(ModuHelp::identifiedOn($request))->toBeFalse();
});

it('pins the bootstrap script text', function () {
    $this->actingAs(renderUser());

    expect(ModuHelp::render(Request::create('/')))
        ->toContain('if(w.__moduhelpBoot)return;')
        ->toContain("d.addEventListener('livewire:navigated',sync)")
        ->toContain("d.addEventListener('turbo:load',sync)");
});

it('component renders the same markup and respects skip', function () {
    $this->actingAs(renderUser());
    $this->app->instance('request', Request::create('/'));

    $this->blade('<x-moduhelp::widget />')->assertSee('name="moduhelp-widget"', false);

    ModuHelp::skip();

    expect((string) $this->blade('<x-moduhelp::widget />'))->not->toContain('moduhelp-widget');
});

function compiledViewFiles(): array
{
    return glob(rtrim(config('view.compiled'), '/').'/*.php') ?: [];
}

it('component writes no per-user html to the compiled views directory', function () {
    array_map('unlink', compiledViewFiles());
    $this->freezeTime();
    $this->actingAs(renderUser());
    $this->app->instance('request', Request::create('/'));
    $token = ModuHelp::token(renderUser());

    $this->blade('<x-moduhelp::widget />');
    $this->blade('<x-moduhelp::widget />');

    $leaks = array_filter(compiledViewFiles(), fn ($file) => str_contains(file_get_contents($file), explode('.', $token)[0]));

    expect($leaks)->toBe([]);
});

it('component does not evaluate blade syntax in config values', function () {
    config()->set('moduhelp.widget_key', 'k{{ 6*7 }}');
    $this->actingAs(renderUser());
    $this->app->instance('request', Request::create('/'));

    $html = (string) $this->blade('<x-moduhelp::widget />');

    expect($html)->not->toContain('k42')->toContain('content="k{{ 6*7 }}"');
});
