<?php

namespace ModuHelp\Laravel;

use Illuminate\Contracts\Config\Repository;

/**
 * Holds configuration access only, never request, user or token state, so it
 * is safe to keep as a long-lived singleton (Octane).
 */
class ModuHelp
{
    private const MIN_TTL = 60;

    private const MAX_TTL = 86400;

    private const DEFAULT_TTL = 43200;

    public function __construct(private readonly Repository $config) {}

    public function enabled(): bool
    {
        $key = $this->config->get('moduhelp.widget_key');

        return is_string($key) && trim($key) !== '';
    }

    public function origin(): string
    {
        $origin = $this->config->get('moduhelp.origin');

        if (! is_string($origin) || trim($origin) === '') {
            $origin = 'https://app.moduhelp.com';
        }

        return rtrim(trim($origin), '/');
    }

    public function ttl(): int
    {
        $ttl = $this->config->get('moduhelp.token_ttl');

        $ttl = is_numeric($ttl) ? (int) $ttl : self::DEFAULT_TTL;

        return max(self::MIN_TTL, min(self::MAX_TTL, $ttl));
    }
}
