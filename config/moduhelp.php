<?php

return [

    /*
    | Public widget key. Leave blank and the package stays fully inert.
    */
    'widget_key' => env('MODUHELP_WIDGET_KEY'),

    /*
    | Secret used to sign identity tokens. Never output. Leave blank to show
    | the widget anonymously.
    */
    'identity_secret' => env('MODUHELP_IDENTITY_SECRET'),

    /*
    | Where the ModuHelp widget is served from. Trailing slash is trimmed.
    */
    'origin' => env('MODUHELP_ORIGIN', 'https://app.moduhelp.com'),

    /*
    | Automatically inject the widget into HTML pages of the "web" group.
    | False turns off injection only; pages that carry a token are still hardened.
    */
    'inject' => env('MODUHELP_INJECT', true),

    /*
    | Identity token lifetime in seconds (clamped to 60..86400).
    */
    'token_ttl' => env('MODUHELP_TOKEN_TTL', 43200),

    /*
    | Auth guard used to resolve the user. Null means the default guard.
    */
    'guard' => env('MODUHELP_GUARD'),

    /*
    | Request path patterns (Request::is) that never get the widget.
    */
    'except' => [
        'horizon*',
        'telescope*',
        'pulse*',
        '_debugbar*',
        'livewire/*',
        'livewire-*',
    ],

];
