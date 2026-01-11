<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Contract;

interface ContractRepositoryInterface
{
    public function findAll(): array;
    public function findById(string $id): ?Contract;
    public function findByProposalId(string $proposalId): ?Contract;
    public function create(Contract $contract): Contract;
}
