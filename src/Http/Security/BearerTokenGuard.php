<?php

declare(strict_types=1);

namespace App\Http\Security;

final readonly class BearerTokenGuard
{
    private const int MIN_TOKEN_LENGTH = 32;

    public function __construct(private string $expectedToken)
    {
    }

    public function isConfigured(): bool
    {
        return strlen($this->expectedToken) >= self::MIN_TOKEN_LENGTH;
    }

    public function allows(?string $authorizationHeader): bool
    {
        if (!$this->isConfigured() || $authorizationHeader === null) {
            return false;
        }

        if (preg_match('/^Bearer\s+([^\s]+)$/i', trim($authorizationHeader), $matches) !== 1) {
            return false;
        }

        return hash_equals($this->expectedToken, $matches[1]);
    }
}
