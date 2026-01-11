<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\ProposalItem;

interface ProposalItemRepositoryInterface
{
    public function findByProposalId(string $proposalId): array;
    public function findById(string $id): ?ProposalItem;
    public function create(ProposalItem $item): ProposalItem;
    public function update(ProposalItem $item): ProposalItem;
    public function delete(string $id): bool;
    public function deleteByProposalId(string $proposalId): bool;
}
