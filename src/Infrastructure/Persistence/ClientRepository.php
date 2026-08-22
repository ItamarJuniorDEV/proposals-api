<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\Client;
use App\Domain\Repositories\ClientRepositoryInterface;
use PDO;

class ClientRepository implements ClientRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<Client> */
    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM clients ORDER BY name");
        $rows = $stmt->fetchAll();

        return array_map($this->toEntity(...), $rows);
    }

    public function findById(string $id): ?Client
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->toEntity($row) : null;
    }

    public function findByEmail(string $email): ?Client
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ? $this->toEntity($row) : null;
    }

    public function create(Client $client): Client
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO clients (name, email, phone, company)
            VALUES (:name, :email, :phone, :company)
            RETURNING *
        ");

        $stmt->execute([
            'name' => $client->getName(),
            'email' => $client->getEmail(),
            'phone' => $client->getPhone(),
            'company' => $client->getCompany(),
        ]);

        return $this->toEntity($stmt->fetch());
    }

    public function update(Client $client): Client
    {
        $stmt = $this->pdo->prepare("
            UPDATE clients
            SET name = :name,
                email = :email,
                phone = :phone,
                company = :company,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
            RETURNING *
        ");

        $stmt->execute([
            'id' => $client->getId(),
            'name' => $client->getName(),
            'email' => $client->getEmail(),
            'phone' => $client->getPhone(),
            'company' => $client->getCompany(),
        ]);

        return $this->toEntity($stmt->fetch());
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM clients WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /** @param array<string, mixed> $row */
    private function toEntity(array $row): Client
    {
        return new Client(
            id: (string) $row['id'],
            name: (string) $row['name'],
            email: (string) $row['email'],
            phone: $row['phone'] !== null ? (string) $row['phone'] : null,
            company: $row['company'] !== null ? (string) $row['company'] : null,
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at']
        );
    }
}
