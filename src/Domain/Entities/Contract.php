<?php

declare(strict_types=1);

namespace App\Domain\Entities;

class Contract
{
    public function __construct(
        private ?string $id,
        private string $proposalId,
        private float $totalAmount,
        private ?string $approvedAt = null,
        private ?string $createdAt = null
    ) {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProposalId(): string
    {
        return $this->proposalId;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getApprovedAt(): ?string
    {
        return $this->approvedAt;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /** @return array{id: ?string, proposal_id: string, total_amount: float, approved_at: ?string, created_at: ?string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'proposal_id' => $this->proposalId,
            'total_amount' => $this->totalAmount,
            'approved_at' => $this->approvedAt,
            'created_at' => $this->createdAt,
        ];
    }
}
