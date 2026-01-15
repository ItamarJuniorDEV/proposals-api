<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Repositories\ContractRepositoryInterface;
use App\Domain\Repositories\ProposalItemRepositoryInterface;
use App\Domain\Repositories\ProposalRepositoryInterface;

class ContractService
{
    public function __construct(
        private ContractRepositoryInterface $contractRepository,
        private ProposalRepositoryInterface $proposalRepository,
        private ProposalItemRepositoryInterface $itemRepository
    ) {
    }

    public function findAll(): array
    {
        return $this->contractRepository->findAll();
    }

    public function findById(string $id): ?array
    {
        $contract = $this->contractRepository->findById($id);

        if (!$contract) {
            return null;
        }

        $proposal = $this->proposalRepository->findById($contract->getProposalId());
        $items = $this->itemRepository->findByProposalId($contract->getProposalId());

        return [
            'contract' => $contract,
            'proposal' => $proposal,
            'items' => $items,
        ];
    }
}
