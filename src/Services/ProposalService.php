<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Entities\Contract;
use App\Domain\Entities\Proposal;
use App\Domain\Entities\ProposalItem;
use App\Domain\Enums\ProposalStatus;
use App\Domain\Repositories\ClientRepositoryInterface;
use App\Domain\Repositories\ContractRepositoryInterface;
use App\Domain\Repositories\ProposalItemRepositoryInterface;
use App\Domain\Repositories\ProposalRepositoryInterface;
use InvalidArgumentException;
use PDO;
use Throwable;

class ProposalService
{
    public function __construct(
        private ProposalRepositoryInterface $proposalRepository,
        private ProposalItemRepositoryInterface $itemRepository,
        private ClientRepositoryInterface $clientRepository,
        private ContractRepositoryInterface $contractRepository,
        private PDO $pdo
    ) {
    }

    public function findAll(): array
    {
        return $this->proposalRepository->findAll();
    }

    public function findById(string $id): ?array
    {
        $proposal = $this->proposalRepository->findById($id);

        if (!$proposal) {
            return null;
        }

        $items = $this->itemRepository->findByProposalId($id);

        return [
            'proposal' => $proposal,
            'items' => $items,
            'totals' => $this->calculateTotals($items, $proposal->getDiscountPercent()),
        ];
    }

    public function findByClientId(string $clientId): array
    {
        return $this->proposalRepository->findByClientId($clientId);
    }

    public function create(array $data): Proposal
    {
        if (empty($data['client_id'])) {
            throw new InvalidArgumentException('Cliente é obrigatório');
        }

        $client = $this->clientRepository->findById($data['client_id']);

        if (!$client) {
            throw new InvalidArgumentException('Cliente não encontrado');
        }

        $proposal = new Proposal(
            id: null,
            clientId: $data['client_id'],
            version: 1,
            parentId: null,
            status: ProposalStatus::Draft,
            validUntil: $data['valid_until'] ?? null,
            discountPercent: (float) ($data['discount_percent'] ?? 0),
            notes: $data['notes'] ?? null
        );

        return $this->proposalRepository->create($proposal);
    }

    public function update(string $id, array $data): Proposal
    {
        $proposal = $this->proposalRepository->findById($id);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canEdit()) {
            throw new InvalidArgumentException('Proposta não pode ser editada');
        }

        $updated = new Proposal(
            id: $id,
            clientId: $proposal->getClientId(),
            version: $proposal->getVersion(),
            parentId: $proposal->getParentId(),
            status: $proposal->getStatus(),
            validUntil: $data['valid_until'] ?? $proposal->getValidUntil(),
            discountPercent: (float) ($data['discount_percent'] ?? $proposal->getDiscountPercent()),
            notes: $data['notes'] ?? $proposal->getNotes(),
            createdAt: $proposal->getCreatedAt()
        );

        return $this->proposalRepository->update($updated);
    }

    public function delete(string $id): void
    {
        $proposal = $this->proposalRepository->findById($id);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canEdit()) {
            throw new InvalidArgumentException('Proposta não pode ser removida');
        }

        $this->proposalRepository->delete($id);
    }

    public function send(string $id): Proposal
    {
        $proposal = $this->proposalRepository->findById($id);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canSend()) {
            throw new InvalidArgumentException('Proposta não pode ser enviada');
        }

        $items = $this->itemRepository->findByProposalId($id);

        if (empty($items)) {
            throw new InvalidArgumentException('Proposta precisa ter pelo menos um item');
        }

        $updated = new Proposal(
            id: $id,
            clientId: $proposal->getClientId(),
            version: $proposal->getVersion(),
            parentId: $proposal->getParentId(),
            status: ProposalStatus::Sent,
            validUntil: $proposal->getValidUntil(),
            discountPercent: $proposal->getDiscountPercent(),
            notes: $proposal->getNotes(),
            createdAt: $proposal->getCreatedAt()
        );

        return $this->proposalRepository->update($updated);
    }

    public function approve(string $id): Contract
    {
        $proposal = $this->proposalRepository->findById($id);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canApprove()) {
            throw new InvalidArgumentException('Proposta não pode ser aprovada');
        }

        if ($proposal->isExpired()) {
            throw new InvalidArgumentException('Proposta expirada');
        }

        $items = $this->itemRepository->findByProposalId($id);
        $totals = $this->calculateTotals($items, $proposal->getDiscountPercent());

        $updated = new Proposal(
            id: $id,
            clientId: $proposal->getClientId(),
            version: $proposal->getVersion(),
            parentId: $proposal->getParentId(),
            status: ProposalStatus::Approved,
            validUntil: $proposal->getValidUntil(),
            discountPercent: $proposal->getDiscountPercent(),
            notes: $proposal->getNotes(),
            createdAt: $proposal->getCreatedAt()
        );

        $this->proposalRepository->update($updated);

        $contract = new Contract(
            id: null,
            proposalId: $id,
            totalAmount: $totals['total']
        );

        return $this->contractRepository->create($contract);
    }

    public function reject(string $id): Proposal
    {
        $proposal = $this->proposalRepository->findById($id);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canReject()) {
            throw new InvalidArgumentException('Proposta não pode ser rejeitada');
        }

        $updated = new Proposal(
            id: $id,
            clientId: $proposal->getClientId(),
            version: $proposal->getVersion(),
            parentId: $proposal->getParentId(),
            status: ProposalStatus::Rejected,
            validUntil: $proposal->getValidUntil(),
            discountPercent: $proposal->getDiscountPercent(),
            notes: $proposal->getNotes(),
            createdAt: $proposal->getCreatedAt()
        );

        return $this->proposalRepository->update($updated);
    }

    public function revise(string $id): Proposal
    {
        $proposal = $this->proposalRepository->findById($id);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canRevise()) {
            throw new InvalidArgumentException('Proposta não pode ser revisada');
        }

        $this->pdo->beginTransaction();

        try {
            $newProposal = new Proposal(
                id: null,
                clientId: $proposal->getClientId(),
                version: $proposal->getVersion() + 1,
                parentId: $id,
                status: ProposalStatus::Draft,
                validUntil: null,
                discountPercent: $proposal->getDiscountPercent(),
                notes: $proposal->getNotes()
            );

            $created = $this->proposalRepository->create($newProposal);

            $items = $this->itemRepository->findByProposalId($id);

            foreach ($items as $item) {
                $newItem = new ProposalItem(
                    id: null,
                    proposalId: $created->getId(),
                    description: $item->getDescription(),
                    quantity: $item->getQuantity(),
                    unitPrice: $item->getUnitPrice()
                );
                $this->itemRepository->create($newItem);
            }

            $this->pdo->commit();

            return $created;
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function addItem(string $proposalId, array $data): ProposalItem
    {
        $proposal = $this->proposalRepository->findById($proposalId);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canEdit()) {
            throw new InvalidArgumentException('Proposta não pode ser editada');
        }

        if (empty($data['description'])) {
            throw new InvalidArgumentException('Descrição é obrigatória');
        }

        if (!isset($data['unit_price']) || $data['unit_price'] <= 0) {
            throw new InvalidArgumentException('Preço unitário inválido');
        }

        $item = new ProposalItem(
            id: null,
            proposalId: $proposalId,
            description: $data['description'],
            quantity: (int) ($data['quantity'] ?? 1),
            unitPrice: (float) $data['unit_price']
        );

        return $this->itemRepository->create($item);
    }

    public function updateItem(string $proposalId, string $itemId, array $data): ProposalItem
    {
        $proposal = $this->proposalRepository->findById($proposalId);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canEdit()) {
            throw new InvalidArgumentException('Proposta não pode ser editada');
        }

        $item = $this->itemRepository->findById($itemId);

        if (!$item || $item->getProposalId() !== $proposalId) {
            throw new InvalidArgumentException('Item não encontrado');
        }

        $updated = new ProposalItem(
            id: $itemId,
            proposalId: $proposalId,
            description: $data['description'] ?? $item->getDescription(),
            quantity: (int) ($data['quantity'] ?? $item->getQuantity()),
            unitPrice: (float) ($data['unit_price'] ?? $item->getUnitPrice()),
            createdAt: $item->getCreatedAt()
        );

        return $this->itemRepository->update($updated);
    }

    public function removeItem(string $proposalId, string $itemId): void
    {
        $proposal = $this->proposalRepository->findById($proposalId);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canEdit()) {
            throw new InvalidArgumentException('Proposta não pode ser editada');
        }

        $item = $this->itemRepository->findById($itemId);

        if (!$item || $item->getProposalId() !== $proposalId) {
            throw new InvalidArgumentException('Item não encontrado');
        }

        $this->itemRepository->delete($itemId);
    }

    private function calculateTotals(array $items, float $discountPercent): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += $item->getSubtotal();
        }

        $discount = $subtotal * ($discountPercent / 100);
        $total = $subtotal - $discount;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
        ];
    }
}
