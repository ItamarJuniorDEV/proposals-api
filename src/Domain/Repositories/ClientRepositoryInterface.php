<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Client;

interface ClientRepositoryInterface
{
    /** @return list<Client> */
    public function findAll(): array;

    public function findById(string $id): ?Client;

    public function findByEmail(string $email): ?Client;

    public function create(Client $client): Client;

    public function update(Client $client): Client;

    public function delete(string $id): bool;
}
