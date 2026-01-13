<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\Contract;
use App\Domain\Repositories\ContractRepositoryInterface;
use PDO;

class ContractRepository implements ContractRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM contracts ORDER BY created_at DESC");
        $rows = $stmt->fetchAll();

        return array_map(fn ($row) => $this->toEntity($row), $rows);
    }

    public function findById(string $id): ?Contract
    {
        $stmt = $this->pdo->prepare("SELECT * FROM contracts WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->toEntity($row) : null;
    }

    public function findByProposalId(string $proposalId): ?Contract
    {
        $stmt = $this->pdo->prepare("SELECT * FROM contracts WHERE proposal_id = :proposal_id");
        $stmt->execute(['proposal_id' => $proposalId]);
        $row = $stmt->fetch();

        return $row ? $this->toEntity($row) : null;
    }

    public function create(Contract $contract): Contract
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO contracts (proposal_id, total_amount)
            VALUES (:proposal_id, :total_amount)
            RETURNING *
        ");

        $stmt->execute([
            'proposal_id' => $contract->getProposalId(),
            'total_amount' => $contract->getTotalAmount(),
        ]);

        return $this->toEntity($stmt->fetch());
    }

    private function toEntity(array $row): Contract
    {
        return new Contract(
            id: $row['id'],
            proposalId: $row['proposal_id'],
            totalAmount: (float) $row['total_amount'],
            approvedAt: $row['approved_at'],
            createdAt: $row['created_at']
        );
    }
}
