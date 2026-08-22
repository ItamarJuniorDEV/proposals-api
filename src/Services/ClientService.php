<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Entities\Client;
use App\Domain\Repositories\ClientRepositoryInterface;
use InvalidArgumentException;

class ClientService
{
    public function __construct(private readonly ClientRepositoryInterface $repository)
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
        $name = $this->requiredString($data['name'] ?? null, 255, 'Nome é obrigatório', 'Nome inválido');
        $email = $this->parseEmail($data['email'] ?? null);
        $phone = $this->optionalString($data['phone'] ?? null, 20, 'Telefone inválido');
        $company = $this->optionalString($data['company'] ?? null, 255, 'Empresa inválida');

        if ($this->repository->findByEmail($email)) {
            throw new InvalidArgumentException('Email já cadastrado');
        }

        return $this->repository->create(new Client(
            id: null,
            name: $name,
            email: $email,
            phone: $phone,
            company: $company
        ));
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): Client
    {
        $client = $this->repository->findById($id);

        if (!$client) {
            throw new InvalidArgumentException('Cliente não encontrado');
        }

        $name = array_key_exists('name', $data)
            ? $this->requiredString($data['name'], 255, 'Nome é obrigatório', 'Nome inválido')
            : $client->getName();
        $email = array_key_exists('email', $data)
            ? $this->parseEmail($data['email'])
            : $client->getEmail();
        $phone = array_key_exists('phone', $data)
            ? $this->optionalString($data['phone'], 20, 'Telefone inválido')
            : $client->getPhone();
        $company = array_key_exists('company', $data)
            ? $this->optionalString($data['company'], 255, 'Empresa inválida')
            : $client->getCompany();

        if ($email !== $client->getEmail() && $this->repository->findByEmail($email)) {
            throw new InvalidArgumentException('Email já cadastrado');
        }

        return $this->repository->update(new Client(
            id: $id,
            name: $name,
            email: $email,
            phone: $phone,
            company: $company,
            createdAt: $client->getCreatedAt()
        ));
    }

    public function delete(string $id): void
    {
        $client = $this->repository->findById($id);

        if (!$client) {
            throw new InvalidArgumentException('Cliente não encontrado');
        }

        $this->repository->delete($id);
    }

    private function parseEmail(mixed $value): string
    {
        $email = $this->requiredString($value, 255, 'Email é obrigatório', 'Email inválido');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email inválido');
        }

        return $email;
    }

    private function requiredString(mixed $value, int $maxLength, string $emptyMessage, string $invalidMessage): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($emptyMessage);
        }

        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException($emptyMessage);
        }

        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException($invalidMessage);
        }

        return $value;
    }

    private function optionalString(mixed $value, int $maxLength, string $invalidMessage): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException($invalidMessage);
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException($invalidMessage);
        }

        return $value;
    }
}
