<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Entities\Contract;
use App\Domain\Entities\Proposal;
use App\Domain\Entities\ProposalItem;
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

    /** @return list<Contract> */
    public function findAll(): array
    {
        return $this->contractRepository->findAll();
    }

    /** @return array{contract: Contract, proposal: Proposal, items: list<ProposalItem>}|null */
    public function findById(string $id): ?array
    {
        $contract = $this->contractRepository->findById($id);

        if (!$contract) {
            return null;
        }

        $proposal = $this->proposalRepository->findById($contract->getProposalId());

        if ($proposal === null) {
            return null;
        }

        $items = $this->itemRepository->findByProposalId($contract->getProposalId());

        return [
            'contract' => $contract,
            'proposal' => $proposal,
            'items' => $items,
        ];
    }
}
