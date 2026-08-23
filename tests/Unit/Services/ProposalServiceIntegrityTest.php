<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Entities\Client;
use App\Domain\Entities\Contract;
use App\Domain\Entities\Proposal;
use App\Domain\Entities\ProposalItem;
use App\Domain\Enums\ProposalStatus;
use App\Domain\Repositories\ClientRepositoryInterface;
use App\Domain\Repositories\ContractRepositoryInterface;
use App\Domain\Repositories\ProposalItemRepositoryInterface;
use App\Domain\Repositories\ProposalRepositoryInterface;
use App\Services\ProposalService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProposalServiceIntegrityTest extends TestCase
{
    private const string CLIENT_ID = '123e4567-e89b-12d3-a456-426614174000';

    private ProposalRepositoryInterface $proposalRepository;
    private ProposalItemRepositoryInterface $itemRepository;
    private ClientRepositoryInterface $clientRepository;
    private ContractRepositoryInterface $contractRepository;
    private PDO $pdo;
    private ProposalService $service;

    protected function setUp(): void
    {
        $this->proposalRepository = $this->createMock(ProposalRepositoryInterface::class);
        $this->itemRepository = $this->createMock(ProposalItemRepositoryInterface::class);
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->contractRepository = $this->createMock(ContractRepositoryInterface::class);
        $this->pdo = $this->createMock(PDO::class);

        $this->service = new ProposalService(
            $this->proposalRepository,
            $this->itemRepository,
            $this->clientRepository,
            $this->contractRepository,
            $this->pdo
        );
    }

    public function testApproveCommitsStatusAndContractTogether(): void
    {
        $proposal = $this->proposal(ProposalStatus::Sent, '2030-12-31', 10);
        $item = new ProposalItem('item-1', 'proposal-123', 'Serviço', 1, 1000.00);
        $contract = new Contract('contract-123', 'proposal-123', 900.00);

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->itemRepository->method('findByProposalId')->willReturn([$item]);
        $this->proposalRepository
            ->expects($this->once())
            ->method('update')
            ->with($this->callback(fn (Proposal $updated) => $updated->getStatus() === ProposalStatus::Approved))
            ->willReturnCallback(fn (Proposal $updated) => $updated);
        $this->contractRepository
            ->expects($this->once())
            ->method('create')
            ->willReturn($contract);

        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->once())->method('commit')->willReturn(true);
        $this->pdo->expects($this->never())->method('rollBack');

        $result = $this->service->approve('proposal-123');

        $this->assertSame('contract-123', $result->getId());
        $this->assertEquals(900.00, $result->getTotalAmount());
    }

    public function testApproveRollsBackWhenContractCreationFails(): void
    {
        $proposal = $this->proposal(ProposalStatus::Sent, '2030-12-31');
        $item = new ProposalItem('item-1', 'proposal-123', 'Serviço', 1, 1000.00);

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->itemRepository->method('findByProposalId')->willReturn([$item]);
        $this->proposalRepository
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(fn (Proposal $updated) => $updated);
        $this->contractRepository
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new RuntimeException('Falha ao persistir contrato'));

        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Falha ao persistir contrato');

        $this->service->approve('proposal-123');
    }

    public function testApproveRejectsDraftAndRollsBackTransaction(): void
    {
        $this->proposalRepository->method('findById')->willReturn($this->proposal(ProposalStatus::Draft));
        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposta não pode ser aprovada');

        $this->service->approve('proposal-123');
    }

    public function testApproveRequiresItems(): void
    {
        $this->proposalRepository->method('findById')->willReturn($this->proposal(ProposalStatus::Sent, '2030-12-31'));
        $this->itemRepository->method('findByProposalId')->willReturn([]);
        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposta precisa ter pelo menos um item');

        $this->service->approve('proposal-123');
    }

    public function testRejectRejectsDraftState(): void
    {
        $this->proposalRepository->method('findById')->willReturn($this->proposal(ProposalStatus::Draft));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposta não pode ser rejeitada');

        $this->service->reject('proposal-123');
    }

    public function testReviseRejectsApprovedStateAndRollsBackTransaction(): void
    {
        $this->proposalRepository->method('findById')->willReturn($this->proposal(ProposalStatus::Approved));
        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposta não pode ser revisada');

        $this->service->revise('proposal-123');
    }

    public function testReviseRejectsWhenSourceAlreadyHasRevision(): void
    {
        $proposal = $this->proposal(ProposalStatus::Sent);
        $revision = new Proposal(
            id: 'proposal-456',
            clientId: 'client-123',
            version: 2,
            parentId: 'proposal-123',
            status: ProposalStatus::Draft,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->proposalRepository->method('findRevisionByParentId')->willReturn($revision);
        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposta já possui uma revisão');

        $this->service->revise('proposal-123');
    }

    public function testReviseRollsBackWhenCopyingAnItemFails(): void
    {
        $proposal = $this->proposal(ProposalStatus::Sent);
        $item = new ProposalItem('item-1', 'proposal-123', 'Serviço', 1, 1000.00);
        $revision = new Proposal(
            id: 'proposal-456',
            clientId: 'client-123',
            version: 2,
            parentId: 'proposal-123',
            status: ProposalStatus::Draft,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->proposalRepository->method('create')->willReturn($revision);
        $this->itemRepository->method('findByProposalId')->willReturn([$item]);
        $this->itemRepository
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new RuntimeException('Falha ao copiar item'));

        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Falha ao copiar item');

        $this->service->revise('proposal-123');
    }

    public function testCreateRejectsDiscountAboveOneHundredPercent(): void
    {
        $client = new Client(self::CLIENT_ID, 'Cliente', 'cliente@example.com', null, null);
        $this->clientRepository->method('findById')->willReturn($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Percentual de desconto inválido');

        $this->service->create([
            'client_id' => self::CLIENT_ID,
            'discount_percent' => 100.01,
        ]);
    }

    public function testUpdateRejectsNegativeDiscount(): void
    {
        $this->proposalRepository->method('findById')->willReturn($this->proposal(ProposalStatus::Draft));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Percentual de desconto inválido');

        $this->service->update('proposal-123', ['discount_percent' => -0.01]);
    }

    public function testAddItemRejectsNonPositiveQuantity(): void
    {
        $this->proposalRepository->method('findById')->willReturn($this->proposal(ProposalStatus::Draft));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantidade inválida');

        $this->service->addItem('proposal-123', [
            'description' => 'Serviço',
            'quantity' => 0,
            'unit_price' => 100,
        ]);
    }

    public function testUpdateItemRejectsNonPositiveUnitPrice(): void
    {
        $proposal = $this->proposal(ProposalStatus::Draft);
        $item = new ProposalItem('item-1', 'proposal-123', 'Serviço', 1, 100.00);

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->itemRepository->method('findById')->willReturn($item);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Preço unitário inválido');

        $this->service->updateItem('proposal-123', 'item-1', ['unit_price' => 0]);
    }

    public function testTotalsAreCalculatedUsingCentPrecision(): void
    {
        $proposal = $this->proposal(ProposalStatus::Draft, null, 7.5);
        $item = new ProposalItem('item-1', 'proposal-123', 'Serviço', 3, 19.99);

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->itemRepository->method('findByProposalId')->willReturn([$item]);

        $result = $this->service->findById('proposal-123');

        $this->assertNotNull($result);
        $this->assertEquals(59.97, $result['totals']['subtotal']);
        $this->assertEquals(4.50, $result['totals']['discount']);
        $this->assertEquals(55.47, $result['totals']['total']);
    }

    private function proposal(
        ProposalStatus $status,
        ?string $validUntil = null,
        float $discountPercent = 0
    ): Proposal {
        return new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: $status,
            validUntil: $validUntil,
            discountPercent: $discountPercent,
            notes: null
        );
    }
}
