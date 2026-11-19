# Changelog

## 0.1.0

- Automatic injection of the ModuHelp widget into full HTML pages of the `web` middleware group, with conservative rules that leave Livewire updates, JSON, downloads, streamed responses, redirects and publicly cacheable pages untouched.
- Signed identity token for the signed-in user (email, name, user id, optional attributes), minted so that the server never receives one it would reject. The widget falls back to anonymous when the secret is blank or the email is unusable.
- `ModuHelp` facade with `identifyUsing`, `showWhen`, `nonceUsing`, `skip`, `token`, `enabled`, `origin` and `renderedOn`.
- `<x-moduhelp::widget />` Blade component for apps that turn auto-injection off or use inline trigger mode.
- Idempotent bootstrap script that survives Livewire `wire:navigate` and Turbo Drive and refreshes the identity on each navigation.
- Pages carrying an identity token leave with `Cache-Control: private, no-store`.
- Support for Laravel 11, 12 and 13 on PHP 8.2 and later (Laravel 13 needs PHP 8.3).
