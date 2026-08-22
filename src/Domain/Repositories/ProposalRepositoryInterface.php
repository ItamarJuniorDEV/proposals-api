<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Proposal;

interface ProposalRepositoryInterface
{
    /** @return list<Proposal> */
    public function findAll(): array;

    public function findById(string $id): ?Proposal;

    /** @return list<Proposal> */
    public function findByClientId(string $clientId): array;

    public function create(Proposal $proposal): Proposal;

    public function update(Proposal $proposal): Proposal;

    public function delete(string $id): bool;
}
