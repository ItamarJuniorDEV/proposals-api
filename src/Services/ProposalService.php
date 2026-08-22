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
        private readonly ProposalRepositoryInterface $proposalRepository,
        private readonly ProposalItemRepositoryInterface $itemRepository,
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly ContractRepositoryInterface $contractRepository,
        private readonly PDO $pdo
    ) {
    }

    /** @return list<Proposal> */
    public function findAll(): array
    {
        return $this->proposalRepository->findAll();
    }

    /** @return array{proposal: Proposal, items: list<ProposalItem>, totals: array{subtotal: float, discount: float, total: float}}|null */
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

    /** @return list<Proposal> */
    public function findByClientId(string $clientId): array
    {
        return $this->proposalRepository->findByClientId($clientId);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Proposal
    {
        if (empty($data['client_id'])) {
            throw new InvalidArgumentException('Cliente é obrigatório');
        }

        $client = $this->clientRepository->findById($data['client_id']);

        if (!$client) {
            throw new InvalidArgumentException('Cliente não encontrado');
        }

        $discountPercent = $this->parseDiscountPercent($data['discount_percent'] ?? 0);

        $proposal = new Proposal(
            id: null,
            clientId: $data['client_id'],
            version: 1,
            parentId: null,
            status: ProposalStatus::Draft,
            validUntil: $data['valid_until'] ?? null,
            discountPercent: $discountPercent,
            notes: $data['notes'] ?? null
        );

        return $this->proposalRepository->create($proposal);
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): Proposal
    {
        $proposal = $this->proposalRepository->findById($id);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canEdit()) {
            throw new InvalidArgumentException('Proposta não pode ser editada');
        }

        $discountPercent = array_key_exists('discount_percent', $data)
            ? $this->parseDiscountPercent($data['discount_percent'])
            : $proposal->getDiscountPercent();

        $updated = new Proposal(
            id: $id,
            clientId: $proposal->getClientId(),
            version: $proposal->getVersion(),
            parentId: $proposal->getParentId(),
            status: $proposal->getStatus(),
            validUntil: $data['valid_until'] ?? $proposal->getValidUntil(),
            discountPercent: $discountPercent,
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

        if (empty($items)) {
            throw new InvalidArgumentException('Proposta precisa ter pelo menos um item');
        }

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

        $this->pdo->beginTransaction();

        try {
            $this->proposalRepository->update($updated);

            $contract = new Contract(
                id: null,
                proposalId: $id,
                totalAmount: $totals['total']
            );

            $created = $this->contractRepository->create($contract);

            $this->pdo->commit();

            return $created;
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
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

    /** @param array<string, mixed> $data */
    public function addItem(string $proposalId, array $data): ProposalItem
    {
        $proposal = $this->proposalRepository->findById($proposalId);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canEdit()) {
            throw new InvalidArgumentException('Proposta não pode ser editada');
        }

        $description = $this->parseDescription($data['description'] ?? null);
        $quantity = $this->parseQuantity($data['quantity'] ?? 1);
        $unitPrice = $this->parseUnitPrice($data['unit_price'] ?? null);

        $item = new ProposalItem(
            id: null,
            proposalId: $proposalId,
            description: $description,
            quantity: $quantity,
            unitPrice: $unitPrice
        );

        return $this->itemRepository->create($item);
    }

    /** @param array<string, mixed> $data */
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

        $description = array_key_exists('description', $data)
            ? $this->parseDescription($data['description'])
            : $item->getDescription();
        $quantity = array_key_exists('quantity', $data)
            ? $this->parseQuantity($data['quantity'])
            : $item->getQuantity();
        $unitPrice = array_key_exists('unit_price', $data)
            ? $this->parseUnitPrice($data['unit_price'])
            : $item->getUnitPrice();

        $updated = new ProposalItem(
            id: $itemId,
            proposalId: $proposalId,
            description: $description,
            quantity: $quantity,
            unitPrice: $unitPrice,
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

    /**
     * @param list<ProposalItem> $items
     * @return array{subtotal: float, discount: float, total: float}
     */
    private function calculateTotals(array $items, float $discountPercent): array
    {
        $subtotalCents = 0;

        foreach ($items as $item) {
            $subtotalCents += $this->moneyToCents($item->getUnitPrice()) * $item->getQuantity();
        }

        $discountBasisPoints = (int) round($discountPercent * 100);
        $discountCents = intdiv(($subtotalCents * $discountBasisPoints) + 5000, 10000);
        $totalCents = $subtotalCents - $discountCents;

        return [
            'subtotal' => $this->centsToMoney($subtotalCents),
            'discount' => $this->centsToMoney($discountCents),
            'total' => $this->centsToMoney($totalCents),
        ];
    }

    private function parseDiscountPercent(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Percentual de desconto inválido');
        }

        $discountPercent = round((float) $value, 2);

        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new InvalidArgumentException('Percentual de desconto inválido');
        }

        return $discountPercent;
    }

    private function parseDescription(mixed $value): string
    {
        $description = is_string($value) ? trim($value) : '';

        if ($description === '') {
            throw new InvalidArgumentException('Descrição é obrigatória');
        }

        return $description;
    }

    private function parseQuantity(mixed $value): int
    {
        $quantity = filter_var($value, FILTER_VALIDATE_INT);

        if ($quantity === false || $quantity < 1) {
            throw new InvalidArgumentException('Quantidade inválida');
        }

        return $quantity;
    }

    private function parseUnitPrice(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Preço unitário inválido');
        }

        $unitPrice = round((float) $value, 2);

        if ($unitPrice <= 0) {
            throw new InvalidArgumentException('Preço unitário inválido');
        }

        return $unitPrice;
    }

    private function moneyToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function centsToMoney(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
