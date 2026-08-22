<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Routes;

use App\Http\Routes\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testResolvesValidUuidParameter(): void
    {
        $router = new Router();
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $router->get('/clients/{id:uuid}', fn (string $id): string => $id);

        $this->assertSame($uuid, $router->resolve('GET', '/clients/'.$uuid));
    }

    public function testRejectsInvalidUuidBeforeHandler(): void
    {
        $router = new Router();
        $called = false;
        $router->get('/clients/{id:uuid}', function () use (&$called): string {
            $called = true;
            return 'called';
        });

        $this->assertSame(['error' => 'Rota não encontrada'], $router->resolve('GET', '/clients/not-a-uuid'));
        $this->assertFalse($called);
    }
}
