<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\Proposal;
use App\Domain\Enums\ProposalStatus;
use App\Domain\Repositories\ProposalRepositoryInterface;
use PDO;

class ProposalRepository implements ProposalRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<Proposal> */
    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM proposals ORDER BY created_at DESC");
        $rows = $stmt->fetchAll();

        return array_map($this->toEntity(...), $rows);
    }

    public function findById(string $id, bool $forUpdate = false): ?Proposal
    {
        $sql = 'SELECT * FROM proposals WHERE id = :id';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->toEntity($row) : null;
    }

    public function findRevisionByParentId(string $parentId): ?Proposal
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM proposals
            WHERE parent_id = :parent_id
            ORDER BY version DESC
            LIMIT 1
        ");
        $stmt->execute(['parent_id' => $parentId]);
        $row = $stmt->fetch();

        return $row ? $this->toEntity($row) : null;
    }

    /** @return list<Proposal> */
    public function findByClientId(string $clientId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM proposals WHERE client_id = :client_id ORDER BY created_at DESC");
        $stmt->execute(['client_id' => $clientId]);
        $rows = $stmt->fetchAll();

        return array_map($this->toEntity(...), $rows);
    }

    public function create(Proposal $proposal): Proposal
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO proposals (client_id, version, parent_id, status, valid_until, discount_percent, notes)
            VALUES (:client_id, :version, :parent_id, :status, :valid_until, :discount_percent, :notes)
            RETURNING *
        ");

        $stmt->execute([
            'client_id' => $proposal->getClientId(),
            'version' => $proposal->getVersion(),
            'parent_id' => $proposal->getParentId(),
            'status' => $proposal->getStatus()->value,
            'valid_until' => $proposal->getValidUntil(),
            'discount_percent' => $proposal->getDiscountPercent(),
            'notes' => $proposal->getNotes(),
        ]);

        return $this->toEntity($stmt->fetch());
    }

    public function update(Proposal $proposal): Proposal
    {
        $stmt = $this->pdo->prepare("
            UPDATE proposals
            SET status = :status,
                valid_until = :valid_until,
                discount_percent = :discount_percent,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
            RETURNING *
        ");

        $stmt->execute([
            'id' => $proposal->getId(),
            'status' => $proposal->getStatus()->value,
            'valid_until' => $proposal->getValidUntil(),
            'discount_percent' => $proposal->getDiscountPercent(),
            'notes' => $proposal->getNotes(),
        ]);

        return $this->toEntity($stmt->fetch());
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM proposals WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /** @param array<string, mixed> $row */
    private function toEntity(array $row): Proposal
    {
        return new Proposal(
            id: (string) $row['id'],
            clientId: (string) $row['client_id'],
            version: (int) $row['version'],
            parentId: $row['parent_id'] !== null ? (string) $row['parent_id'] : null,
            status: ProposalStatus::from((string) $row['status']),
            validUntil: $row['valid_until'] !== null ? (string) $row['valid_until'] : null,
            discountPercent: (float) $row['discount_percent'],
            notes: $row['notes'] !== null ? (string) $row['notes'] : null,
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at']
        );
    }
}
