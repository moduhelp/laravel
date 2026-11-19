# Changelog

## 0.1.2

- Fix: injecting the widget replaced the response's original View with the HTML string, so `assertViewHas()`, `viewData()` and any later middleware reading the view broke on injected pages. The original is now kept.

## 0.1.1

- Fix: a blank `MODUHELP_INJECT=` line in `.env` silently turned auto-injection off, because Laravel reads it as an empty string. Unset, blank or unrecognised values now keep injection on; only `false`, `off`, `no` or `0` turn it off. New `ModuHelp::injects()` reports the effective value.

## 0.1.0

- Automatic injection of the ModuHelp widget into full HTML pages of the `web` middleware group, with conservative rules that leave Livewire updates, JSON, downloads, streamed responses, redirects and publicly cacheable pages untouched.
- Signed identity token for the signed-in user (email, name, user id, optional attributes), minted so that the server never receives one it would reject. The widget falls back to anonymous when the secret is blank or the email is unusable.
- `ModuHelp` facade with `identifyUsing`, `showWhen`, `nonceUsing`, `skip`, `token`, `enabled`, `origin` and `renderedOn`.
- `<x-moduhelp::widget />` Blade component for apps that turn auto-injection off. The floating and custom trigger modes are supported; inline trigger mode is not.
- Idempotent bootstrap script that survives Livewire `wire:navigate` and Turbo Drive and refreshes the identity on each navigation.
- Pages on which an identity token was rendered, by auto-injection or by the component, leave with `Cache-Control: private, no-store` and without `ETag` or `Last-Modified`, also when `MODUHELP_INJECT=false`. Applies to routes in the `web` group.
- Default `except` list covers the Livewire 3 (`livewire/*`) and Livewire 4 (`livewire-*`) endpoints.
- Support for Laravel 11, 12 and 13 on PHP 8.2 and later (Laravel 13 needs PHP 8.3).
