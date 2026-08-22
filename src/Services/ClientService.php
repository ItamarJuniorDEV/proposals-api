<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Entities\Client;
use App\Domain\Repositories\ClientRepositoryInterface;
use InvalidArgumentException;

class ClientService
{
    public function __construct(private ClientRepositoryInterface $repository)
    {
    }

    /** @return list<Client> */
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function findById(string $id): ?Client
    {
        return $this->repository->findById($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Client
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Nome é obrigatório');
        }

        if (empty($data['email'])) {
            throw new InvalidArgumentException('Email é obrigatório');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email inválido');
        }

        if ($this->repository->findByEmail($data['email'])) {
            throw new InvalidArgumentException('Email já cadastrado');
        }

        $client = new Client(
            id: null,
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            company: $data['company'] ?? null
        );

        return $this->repository->create($client);
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): Client
    {
        $client = $this->repository->findById($id);

        if (!$client) {
            throw new InvalidArgumentException('Cliente não encontrado');
        }

        $email = $data['email'] ?? $client->getEmail();

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email inválido');
        }

        if (isset($data['email']) && $data['email'] !== $client->getEmail()) {
            if ($this->repository->findByEmail($data['email'])) {
                throw new InvalidArgumentException('Email já cadastrado');
            }
        }

        $updated = new Client(
            id: $id,
            name: $data['name'] ?? $client->getName(),
            email: $email,
            phone: $data['phone'] ?? $client->getPhone(),
            company: $data['company'] ?? $client->getCompany(),
            createdAt: $client->getCreatedAt()
        );

        return $this->repository->update($updated);
    }

    public function delete(string $id): void
    {
        $client = $this->repository->findById($id);

        if (!$client) {
            throw new InvalidArgumentException('Cliente não encontrado');
        }

        $this->repository->delete($id);
    }
}
