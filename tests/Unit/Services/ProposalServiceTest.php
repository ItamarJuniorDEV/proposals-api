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

class ProposalServiceTest extends TestCase
{
    private ProposalRepositoryInterface $proposalRepository;
    private ProposalItemRepositoryInterface $itemRepository;
    private ClientRepositoryInterface $clientRepository;
    private ContractRepositoryInterface $contractRepository;
    private ProposalService $service;

    protected function setUp(): void
    {
        $this->proposalRepository = $this->createMock(ProposalRepositoryInterface::class);
        $this->itemRepository = $this->createMock(ProposalItemRepositoryInterface::class);
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->contractRepository = $this->createMock(ContractRepositoryInterface::class);

        $this->service = new ProposalService(
            $this->proposalRepository,
            $this->itemRepository,
            $this->clientRepository,
            $this->contractRepository,
            $this->createMock(PDO::class)
        );
    }

    public function testCreateProposalSuccess(): void
    {
        $clientId = '123e4567-e89b-12d3-a456-426614174000';
        $client = new Client($clientId, 'João', 'joao@email.com', null, null);

        $expectedProposal = new Proposal(
            id: 'proposal-123',
            clientId: $clientId,
            version: 1,
            parentId: null,
            status: ProposalStatus::Draft,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->clientRepository->method('findById')->willReturn($client);
        $this->proposalRepository->method('create')->willReturn($expectedProposal);

        $proposal = $this->service->create(['client_id' => $clientId]);

        $this->assertEquals('proposal-123', $proposal->getId());
        $this->assertEquals(ProposalStatus::Draft, $proposal->getStatus());
    }

    public function testCreateProposalWithoutClient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cliente é obrigatório');

        $this->service->create([]);
    }

    public function testCreateProposalClientNotFound(): void
    {
        $this->clientRepository->method('findById')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cliente não encontrado');

        $this->service->create(['client_id' => '123e4567-e89b-12d3-a456-426614174999']);
    }

    public function testSendProposalSuccess(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Draft,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $item = new ProposalItem('item-1', 'proposal-123', 'Serviço', 1, 1000.00);

        $sentProposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Sent,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->itemRepository->method('findByProposalId')->willReturn([$item]);
        $this->proposalRepository->method('update')->willReturn($sentProposal);

        $result = $this->service->send('proposal-123');

        $this->assertEquals(ProposalStatus::Sent, $result->getStatus());
    }

    public function testSendProposalWithoutItems(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Draft,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->itemRepository->method('findByProposalId')->willReturn([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposta precisa ter pelo menos um item');

        $this->service->send('proposal-123');
    }

    public function testSendProposalAlreadySent(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Sent,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposta não pode ser enviada');

        $this->service->send('proposal-123');
    }

    public function testApproveProposalSuccess(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Sent,
            validUntil: '2030-12-31',
            discountPercent: 10,
            notes: null
        );

        $item = new ProposalItem('item-1', 'proposal-123', 'Serviço', 1, 1000.00);

        $contract = new Contract(
            id: 'contract-123',
            proposalId: 'proposal-123',
            totalAmount: 900.00
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->itemRepository->method('findByProposalId')->willReturn([$item]);
        $this->contractRepository->method('create')->willReturn($contract);

        $result = $this->service->approve('proposal-123');

        $this->assertEquals('contract-123', $result->getId());
        $this->assertEquals(900.00, $result->getTotalAmount());
    }

    public function testApproveExpiredProposal(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Sent,
            validUntil: '2020-01-01',
            discountPercent: 0,
            notes: null
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposta expirada');

        $this->service->approve('proposal-123');
    }

    public function testRejectProposalSuccess(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Sent,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $rejectedProposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Rejected,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->proposalRepository->method('update')->willReturn($rejectedProposal);

        $result = $this->service->reject('proposal-123');

        $this->assertEquals(ProposalStatus::Rejected, $result->getStatus());
    }

    public function testReviseProposalCreatesNewVersion(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Sent,
            validUntil: null,
            discountPercent: 10,
            notes: 'Nota original'
        );

        $item = new ProposalItem('item-1', 'proposal-123', 'Serviço', 1, 1000.00);

        $newProposal = new Proposal(
            id: 'proposal-456',
            clientId: 'client-123',
            version: 2,
            parentId: 'proposal-123',
            status: ProposalStatus::Draft,
            validUntil: null,
            discountPercent: 10,
            notes: 'Nota original'
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->proposalRepository->method('create')->willReturn($newProposal);
        $this->itemRepository->method('findByProposalId')->willReturn([$item]);

        $result = $this->service->revise('proposal-123');

        $this->assertEquals(2, $result->getVersion());
        $this->assertEquals('proposal-123', $result->getParentId());
        $this->assertEquals(ProposalStatus::Draft, $result->getStatus());
    }

    public function testAddItemSuccess(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Draft,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $item = new ProposalItem(
            id: 'item-123',
            proposalId: 'proposal-123',
            description: 'Desenvolvimento',
            quantity: 1,
            unitPrice: 5000.00
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);
        $this->itemRepository->method('create')->willReturn($item);

        $result = $this->service->addItem('proposal-123', [
            'description' => 'Desenvolvimento',
            'quantity' => 1,
            'unit_price' => 5000.00
        ]);

        $this->assertEquals('Desenvolvimento', $result->getDescription());
        $this->assertEquals(5000.00, $result->getUnitPrice());
    }

    public function testAddItemToSentProposal(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Sent,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposta não pode ser editada');

        $this->service->addItem('proposal-123', [
            'description' => 'Item',
            'unit_price' => 100
        ]);
    }

    public function testAddItemWithoutDescription(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Draft,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Descrição é obrigatória');

        $this->service->addItem('proposal-123', ['unit_price' => 100]);
    }

    public function testAddItemWithInvalidPrice(): void
    {
        $proposal = new Proposal(
            id: 'proposal-123',
            clientId: 'client-123',
            version: 1,
            parentId: null,
            status: ProposalStatus::Draft,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->proposalRepository->method('findById')->willReturn($proposal);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Preço unitário inválido');

        $this->service->addItem('proposal-123', [
            'description' => 'Item',
            'unit_price' => 0
        ]);
    }
}
