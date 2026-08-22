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
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

class ProposalService
{
    private const MAX_NOTES_LENGTH = 5000;
    private const MAX_DESCRIPTION_LENGTH = 255;
    private const MAX_QUANTITY = 1000000;
    private const MAX_UNIT_PRICE = 99999999.99;

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
        $clientId = $this->parseClientId($data['client_id'] ?? null);
        $client = $this->clientRepository->findById($clientId);

        if (!$client) {
            throw new InvalidArgumentException('Cliente não encontrado');
        }

        $proposal = new Proposal(
            id: null,
            clientId: $clientId,
            version: 1,
            parentId: null,
            status: ProposalStatus::Draft,
            validUntil: $this->parseDate($data['valid_until'] ?? null),
            discountPercent: $this->parseDiscountPercent($data['discount_percent'] ?? 0),
            notes: $this->parseNotes($data['notes'] ?? null)
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
        $validUntil = array_key_exists('valid_until', $data)
            ? $this->parseDate($data['valid_until'])
            : $proposal->getValidUntil();
        $notes = array_key_exists('notes', $data)
            ? $this->parseNotes($data['notes'])
            : $proposal->getNotes();

        return $this->proposalRepository->update(new Proposal(
            id: $id,
            clientId: $proposal->getClientId(),
            version: $proposal->getVersion(),
            parentId: $proposal->getParentId(),
            status: $proposal->getStatus(),
            validUntil: $validUntil,
            discountPercent: $discountPercent,
            notes: $notes,
            createdAt: $proposal->getCreatedAt()
        ));
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

        return $this->proposalRepository->update(new Proposal(
            id: $id,
            clientId: $proposal->getClientId(),
            version: $proposal->getVersion(),
            parentId: $proposal->getParentId(),
            status: ProposalStatus::Sent,
            validUntil: $proposal->getValidUntil(),
            discountPercent: $proposal->getDiscountPercent(),
            notes: $proposal->getNotes(),
            createdAt: $proposal->getCreatedAt()
        ));
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
            $created = $this->contractRepository->create(new Contract(
                id: null,
                proposalId: $id,
                totalAmount: $totals['total']
            ));
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

        return $this->proposalRepository->update(new Proposal(
            id: $id,
            clientId: $proposal->getClientId(),
            version: $proposal->getVersion(),
            parentId: $proposal->getParentId(),
            status: ProposalStatus::Rejected,
            validUntil: $proposal->getValidUntil(),
            discountPercent: $proposal->getDiscountPercent(),
            notes: $proposal->getNotes(),
            createdAt: $proposal->getCreatedAt()
        ));
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
            $created = $this->proposalRepository->create(new Proposal(
                id: null,
                clientId: $proposal->getClientId(),
                version: $proposal->getVersion() + 1,
                parentId: $id,
                status: ProposalStatus::Draft,
                validUntil: null,
                discountPercent: $proposal->getDiscountPercent(),
                notes: $proposal->getNotes()
            ));

            foreach ($this->itemRepository->findByProposalId($id) as $item) {
                $this->itemRepository->create(new ProposalItem(
                    id: null,
                    proposalId: $created->getId(),
                    description: $item->getDescription(),
                    quantity: $item->getQuantity(),
                    unitPrice: $item->getUnitPrice()
                ));
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
        $proposal = $this->editableProposal($proposalId);
        $description = $this->parseDescription($data['description'] ?? null);
        $quantity = $this->parseQuantity($data['quantity'] ?? 1);
        $unitPrice = $this->parseUnitPrice($data['unit_price'] ?? null);

        return $this->itemRepository->create(new ProposalItem(
            id: null,
            proposalId: $proposalId,
            description: $description,
            quantity: $quantity,
            unitPrice: $unitPrice
        ));
    }

    /** @param array<string, mixed> $data */
    public function updateItem(string $proposalId, string $itemId, array $data): ProposalItem
    {
        $this->editableProposal($proposalId);
        $item = $this->itemRepository->findById($itemId);

        if (!$item || $item->getProposalId() !== $proposalId) {
            throw new InvalidArgumentException('Item não encontrado');
        }

        return $this->itemRepository->update(new ProposalItem(
            id: $itemId,
            proposalId: $proposalId,
            description: array_key_exists('description', $data) ? $this->parseDescription($data['description']) : $item->getDescription(),
            quantity: array_key_exists('quantity', $data) ? $this->parseQuantity($data['quantity']) : $item->getQuantity(),
            unitPrice: array_key_exists('unit_price', $data) ? $this->parseUnitPrice($data['unit_price']) : $item->getUnitPrice(),
            createdAt: $item->getCreatedAt()
        ));
    }

    public function removeItem(string $proposalId, string $itemId): void
    {
        $this->editableProposal($proposalId);
        $item = $this->itemRepository->findById($itemId);

        if (!$item || $item->getProposalId() !== $proposalId) {
            throw new InvalidArgumentException('Item não encontrado');
        }

        $this->itemRepository->delete($itemId);
    }

    private function editableProposal(string $proposalId): Proposal
    {
        $proposal = $this->proposalRepository->findById($proposalId);

        if (!$proposal) {
            throw new InvalidArgumentException('Proposta não encontrada');
        }

        if (!$proposal->getStatus()->canEdit()) {
            throw new InvalidArgumentException('Proposta não pode ser editada');
        }

        return $proposal;
    }

    /**
     * @param list<ProposalItem> $items
     * @return array{subtotal: float, discount: float, total: float}
     */
    private function calculateTotals(array $items, float $discountPercent): array
    {
        $subtotalCents = 0;

        foreach ($items as $item) {
            $itemCents = $this->moneyToCents($item->getUnitPrice());

            if ($item->getQuantity() > intdiv(PHP_INT_MAX, max(1, $itemCents))) {
                throw new InvalidArgumentException('Total da proposta excede o limite permitido');
            }

            $lineCents = $itemCents * $item->getQuantity();

            if ($subtotalCents > PHP_INT_MAX - $lineCents) {
                throw new InvalidArgumentException('Total da proposta excede o limite permitido');
            }

            $subtotalCents += $lineCents;
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

    private function parseClientId(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Cliente é obrigatório');
        }

        $value = trim($value);

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new InvalidArgumentException('Cliente inválido');
        }

        return $value;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Data de validade inválida');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Data de validade inválida');
        }

        return $value;
    }

    private function parseNotes(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || strlen($value) > self::MAX_NOTES_LENGTH) {
            throw new InvalidArgumentException('Observações inválidas');
        }

        return trim($value);
    }

    private function parseDiscountPercent(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Percentual de desconto inválido');
        }

        $discountPercent = (float) $value;

        if (!is_finite($discountPercent) || $discountPercent < 0 || $discountPercent > 100) {
            throw new InvalidArgumentException('Percentual de desconto inválido');
        }

        return round($discountPercent, 2);
    }

    private function parseDescription(mixed $value): string
    {
        $description = is_string($value) ? trim($value) : '';

        if ($description === '') {
            throw new InvalidArgumentException('Descrição é obrigatória');
        }

        if (strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw new InvalidArgumentException('Descrição inválida');
        }

        return $description;
    }

    private function parseQuantity(mixed $value): int
    {
        $quantity = filter_var($value, FILTER_VALIDATE_INT);

        if ($quantity === false || $quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidArgumentException('Quantidade inválida');
        }

        return $quantity;
    }

    private function parseUnitPrice(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Preço unitário inválido');
        }

        $unitPrice = (float) $value;

        if (!is_finite($unitPrice) || $unitPrice <= 0 || $unitPrice > self::MAX_UNIT_PRICE) {
            throw new InvalidArgumentException('Preço unitário inválido');
        }

        return round($unitPrice, 2);
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
