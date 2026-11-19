<?php

namespace ModuHelp\Laravel\Identity;

use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Mints the signed identity token for a signed-in user:
 *
 *     base64url(json payload) . "." . hex(hmac_sha256(payload_b64, secret))
 *
 * Host input is untrusted and of any type. Every claim is normalised so the
 * server accepts the token; anything that cannot be made acceptable yields
 * null (anonymous widget) instead of a token that would be refused. Never
 * throws and never logs.
 */
final class TokenMinter
{
    public const MAX_LENGTH = 4096;

    public const MAX_ATTRS = 10;

    public const MAX_ATTR_STRING_LENGTH = 200;

    public const MAX_NAME_LENGTH = 100;

    public const MAX_UID_LENGTH = 191;

    public const ATTR_KEY_PATTERN = '/^(?![0-9]+$)[a-z0-9_]{1,40}$/';

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public function __construct(
        private readonly string $widgetKey,
        private readonly string $secret,
        private readonly int $ttl,
    ) {}

    /**
     * @param  array<string, mixed>  $claims  email, name, uid, attrs (any type)
     */
    public function mint(array $claims): ?string
    {
        try {
            $email = $this->email($claims['email'] ?? null);

            if ($email === null) {
                return null;
            }

            $payload = ['k' => $this->widgetKey, 'email' => $email];

            $name = $this->name($claims['name'] ?? null);

            if ($name !== null) {
                $payload['name'] = $name;
            }

            $uid = $this->uid($claims['uid'] ?? null);

            if ($uid !== null) {
                $payload['uid'] = $uid;
            }

            $attrs = $this->attrs($claims['attrs'] ?? null);
            $exp = now()->getTimestamp() + $this->ttl;

            if ($attrs !== []) {
                $token = $this->sign($payload + ['attrs' => $attrs, 'exp' => $exp]);

                if (strlen($token) <= self::MAX_LENGTH) {
                    return $token;
                }
            }

            $token = $this->sign($payload + ['exp' => $exp]);

            return strlen($token) <= self::MAX_LENGTH ? $token : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $payload */
    private function sign(array $payload): string
    {
        $segment = rtrim(strtr(base64_encode(json_encode($payload, self::JSON_FLAGS)), '+/', '-_'), '=');

        return $segment.'.'.hash_hmac('sha256', $segment, $this->secret);
    }

    private function email(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if (preg_match('/^[\x20-\x7E]*$/', $trimmed) !== 1) {
            return null;
        }

        $email = strtolower($trimmed);

        $valid = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'string', 'email:rfc', 'max:254']],
        )->passes();

        return $valid ? $email : null;
    }

    private function name(mixed $value): ?string
    {
        return is_scalar($value) ? $this->line((string) $value, self::MAX_NAME_LENGTH) : null;
    }

    private function uid(mixed $value): string|int|null
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) ? $this->line($value, self::MAX_UID_LENGTH) : null;
    }

    /**
     * One line, cut to $max characters, null when nothing is left. Invalid
     * UTF-8 throws, which mint() turns into a null token as the spec requires.
     */
    private function line(string $value, int $max): ?string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new \InvalidArgumentException('Invalid UTF-8');
        }

        $clean = trim((string) preg_replace('/\s+/u', ' ', $value));
        $clean = mb_substr($clean, 0, $max);

        return $clean === '' ? null : $clean;
    }

    /** @return array<string, string|int|float|bool> */
    private function attrs(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            return [];
        }

        $attrs = [];

        foreach ($value as $key => $item) {
            if (count($attrs) >= self::MAX_ATTRS) {
                break;
            }

            if (! is_string($key) || preg_match(self::ATTR_KEY_PATTERN, $key) !== 1) {
                continue;
            }

            if (! is_scalar($item)) {
                continue;
            }

            if (is_string($item) && mb_strlen($item) > self::MAX_ATTR_STRING_LENGTH) {
                continue;
            }

            $attrs[$key] = $item;
        }

        return $attrs;
    }
}
