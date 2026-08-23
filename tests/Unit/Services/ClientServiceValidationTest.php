<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Repositories\ClientRepositoryInterface;
use App\Services\ClientService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ClientServiceValidationTest extends TestCase
{
    public function testRejectsInvalidClientFieldTypesAndLengths(): void
    {
        $service = new ClientService($this->createMock(ClientRepositoryInterface::class));

        foreach ([
            ['name' => ['not-a-string'], 'email' => 'test@example.com'],
            ['name' => 'Client', 'email' => 'test@example.com', 'phone' => str_repeat('1', 21)],
            ['name' => str_repeat('a', 256), 'email' => 'test@example.com'],
        ] as $payload) {
            try {
                $service->create($payload);
                $this->fail('Payload inválido foi aceito');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
