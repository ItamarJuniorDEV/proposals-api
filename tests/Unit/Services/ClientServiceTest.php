<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Entities\Client;
use App\Domain\Repositories\ClientRepositoryInterface;
use App\Services\ClientService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ClientServiceTest extends TestCase
{
    private ClientRepositoryInterface $repository;
    private ClientService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ClientRepositoryInterface::class);
        $this->service = new ClientService($this->repository);
    }

    public function testCreateClientSuccess(): void
    {
        $data = [
            'name' => 'João Silva',
            'email' => 'joao@email.com',
            'phone' => '11999999999',
            'company' => 'Empresa X'
        ];

        $expectedClient = new Client(
            id: 'uuid-123',
            name: 'João Silva',
            email: 'joao@email.com',
            phone: '11999999999',
            company: 'Empresa X'
        );

        $this->repository->method('findByEmail')->willReturn(null);
        $this->repository->method('create')->willReturn($expectedClient);

        $client = $this->service->create($data);

        $this->assertEquals('João Silva', $client->getName());
        $this->assertEquals('joao@email.com', $client->getEmail());
    }

    public function testCreateClientWithoutName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nome é obrigatório');

        $this->service->create(['email' => 'test@email.com']);
    }

    public function testCreateClientWithoutEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email é obrigatório');

        $this->service->create(['name' => 'João']);
    }

    public function testCreateClientWithInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email inválido');

        $this->service->create([
            'name' => 'João',
            'email' => 'email-invalido'
        ]);
    }

    public function testCreateClientWithDuplicateEmail(): void
    {
        $existingClient = new Client(
            id: 'uuid-123',
            name: 'Outro',
            email: 'joao@email.com',
            phone: null,
            company: null
        );

        $this->repository->method('findByEmail')->willReturn($existingClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email já cadastrado');

        $this->service->create([
            'name' => 'João',
            'email' => 'joao@email.com'
        ]);
    }

    public function testUpdateClientNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cliente não encontrado');

        $this->service->update('uuid-inexistente', ['name' => 'Novo Nome']);
    }

    public function testDeleteClientNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cliente não encontrado');

        $this->service->delete('uuid-inexistente');
    }
}
