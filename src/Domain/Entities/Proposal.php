<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Enums\ProposalStatus;

class Proposal
{
    public function __construct(
        private ?string $id,
        private string $clientId,
        private int $version,
        private ?string $parentId,
        private ProposalStatus $status,
        private ?string $validUntil,
        private float $discountPercent,
        private ?string $notes,
        private ?string $createdAt = null,
        private ?string $updatedAt = null
    ) {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getStatus(): ProposalStatus
    {
        return $this->status;
    }

    public function getValidUntil(): ?string
    {
        return $this->validUntil;
    }

    public function getDiscountPercent(): float
    {
        return $this->discountPercent;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function isExpired(): bool
    {
        if ($this->validUntil === null) {
            return false;
        }

        return strtotime($this->validUntil) < strtotime('today');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'version' => $this->version,
            'parent_id' => $this->parentId,
            'status' => $this->status->value,
            'valid_until' => $this->validUntil,
            'discount_percent' => $this->discountPercent,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
