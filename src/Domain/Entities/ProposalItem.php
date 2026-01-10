<?php

declare(strict_types=1);

namespace App\Domain\Entities;

class ProposalItem
{
    public function __construct(
        private ?string $id,
        private string $proposalId,
        private string $description,
        private int $quantity,
        private float $unitPrice,
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getSubtotal(): float
    {
        return round($this->quantity * $this->unitPrice, 2);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'proposal_id' => $this->proposalId,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'subtotal' => $this->getSubtotal(),
            'created_at' => $this->createdAt,
        ];
    }
}
