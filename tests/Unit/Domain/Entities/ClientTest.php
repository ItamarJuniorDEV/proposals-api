<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testCreateClient(): void
    {
        $client = new Client(
            id: 'uuid-123',
            name: 'João Silva',
            email: 'joao@email.com',
            phone: '11999999999',
            company: 'Empresa X'
        );

        $this->assertEquals('uuid-123', $client->getId());
        $this->assertEquals('João Silva', $client->getName());
        $this->assertEquals('joao@email.com', $client->getEmail());
        $this->assertEquals('11999999999', $client->getPhone());
        $this->assertEquals('Empresa X', $client->getCompany());
    }

    public function testCreateClientWithoutOptionalFields(): void
    {
        $client = new Client(
            id: null,
            name: 'Maria',
            email: 'maria@email.com',
            phone: null,
            company: null
        );

        $this->assertNull($client->getId());
        $this->assertNull($client->getPhone());
        $this->assertNull($client->getCompany());
    }

    public function testToArray(): void
    {
        $client = new Client(
            id: 'uuid-123',
            name: 'João Silva',
            email: 'joao@email.com',
            phone: '11999999999',
            company: 'Empresa X',
            createdAt: '2025-01-01 10:00:00',
            updatedAt: '2025-01-01 10:00:00'
        );

        $array = $client->toArray();

        $this->assertEquals('uuid-123', $array['id']);
        $this->assertEquals('João Silva', $array['name']);
        $this->assertEquals('joao@email.com', $array['email']);
        $this->assertEquals('11999999999', $array['phone']);
        $this->assertEquals('Empresa X', $array['company']);
    }
}
