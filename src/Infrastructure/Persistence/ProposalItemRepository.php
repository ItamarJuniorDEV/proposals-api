<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\ProposalItem;
use App\Domain\Repositories\ProposalItemRepositoryInterface;
use PDO;

class ProposalItemRepository implements ProposalItemRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<ProposalItem> */
    public function findByProposalId(string $proposalId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM proposal_items WHERE proposal_id = :proposal_id ORDER BY created_at");
        $stmt->execute(['proposal_id' => $proposalId]);
        $rows = $stmt->fetchAll();

        return array_map($this->toEntity(...), $rows);
    }

    public function findById(string $id): ?ProposalItem
    {
        $stmt = $this->pdo->prepare("SELECT * FROM proposal_items WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->toEntity($row) : null;
    }

    public function create(ProposalItem $item): ProposalItem
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO proposal_items (proposal_id, description, quantity, unit_price)
            VALUES (:proposal_id, :description, :quantity, :unit_price)
            RETURNING *
        ");

        $stmt->execute([
            'proposal_id' => $item->getProposalId(),
            'description' => $item->getDescription(),
            'quantity' => $item->getQuantity(),
            'unit_price' => $item->getUnitPrice(),
        ]);

        return $this->toEntity($stmt->fetch());
    }

    public function update(ProposalItem $item): ProposalItem
    {
        $stmt = $this->pdo->prepare("
            UPDATE proposal_items
            SET description = :description,
                quantity = :quantity,
                unit_price = :unit_price
            WHERE id = :id
            RETURNING *
        ");

        $stmt->execute([
            'id' => $item->getId(),
            'description' => $item->getDescription(),
            'quantity' => $item->getQuantity(),
            'unit_price' => $item->getUnitPrice(),
        ]);

        return $this->toEntity($stmt->fetch());
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM proposal_items WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByProposalId(string $proposalId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM proposal_items WHERE proposal_id = :proposal_id");
        $stmt->execute(['proposal_id' => $proposalId]);

        return $stmt->rowCount() > 0;
    }

    /** @param array<string, mixed> $row */
    private function toEntity(array $row): ProposalItem
    {
        return new ProposalItem(
            id: (string) $row['id'],
            proposalId: (string) $row['proposal_id'],
            description: (string) $row['description'],
            quantity: (int) $row['quantity'],
            unitPrice: (float) $row['unit_price'],
            createdAt: (string) $row['created_at']
        );
    }
}
