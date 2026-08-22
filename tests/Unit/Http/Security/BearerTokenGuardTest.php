<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Security;

use App\Http\Security\BearerTokenGuard;
use PHPUnit\Framework\TestCase;

class BearerTokenGuardTest extends TestCase
{
    private const TOKEN = '0123456789abcdef0123456789abcdef';

    public function testRequiresTokenWithMinimumLength(): void
    {
        $this->assertFalse((new BearerTokenGuard('short'))->isConfigured());
        $this->assertTrue((new BearerTokenGuard(self::TOKEN))->isConfigured());
    }

    public function testAcceptsConfiguredBearerToken(): void
    {
        $guard = new BearerTokenGuard(self::TOKEN);

        $this->assertTrue($guard->allows('Bearer '.self::TOKEN));
    }

    public function testRejectsMissingMalformedOrDifferentToken(): void
    {
        $guard = new BearerTokenGuard(self::TOKEN);

        $this->assertFalse($guard->allows(null));
        $this->assertFalse($guard->allows(self::TOKEN));
        $this->assertFalse($guard->allows('Basic '.self::TOKEN));
        $this->assertFalse($guard->allows('Bearer 0123456789abcdef0123456789abcdee'));
    }
}
