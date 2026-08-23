<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Entities\Client;
use App\Domain\Entities\Proposal;
use App\Domain\Enums\ProposalStatus;
use App\Domain\Repositories\ClientRepositoryInterface;
use App\Domain\Repositories\ContractRepositoryInterface;
use App\Domain\Repositories\ProposalItemRepositoryInterface;
use App\Domain\Repositories\ProposalRepositoryInterface;
use App\Services\ProposalService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

class ProposalInputValidationTest extends TestCase
{
    private ProposalRepositoryInterface $proposalRepository;
    private ProposalItemRepositoryInterface $itemRepository;
    private ClientRepositoryInterface $clientRepository;
    private ProposalService $service;

    protected function setUp(): void
    {
        $this->proposalRepository = $this->createMock(ProposalRepositoryInterface::class);
        $this->itemRepository = $this->createMock(ProposalItemRepositoryInterface::class);
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->service = new ProposalService(
            $this->proposalRepository,
            $this->itemRepository,
            $this->clientRepository,
            $this->createMock(ContractRepositoryInterface::class),
            $this->createMock(PDO::class)
        );
    }

    public function testRejectsInvalidClientUuidBeforeRepository(): void
    {
        $this->clientRepository->expects($this->never())->method('findById');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cliente inválido');

        $this->service->create(['client_id' => 'not-a-uuid']);
    }

    public function testRejectsInvalidDateAndOversizedNotes(): void
    {
        $clientId = '123e4567-e89b-12d3-a456-426614174000';
        $this->clientRepository->method('findById')->willReturn(new Client($clientId, 'Client', 'client@example.com', null, null));

        foreach ([
            ['client_id' => $clientId, 'valid_until' => '2026-02-31'],
            ['client_id' => $clientId, 'notes' => str_repeat('a', 5001)],
        ] as $payload) {
            try {
                $this->service->create($payload);
                $this->fail('Payload inválido foi aceito');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRejectsOversizedDescriptionAndQuantity(): void
    {
        $proposalId = '123e4567-e89b-12d3-a456-426614174001';
        $this->proposalRepository->method('findById')->willReturn(new Proposal(
            $proposalId,
            '123e4567-e89b-12d3-a456-426614174000',
            1,
            null,
            ProposalStatus::Draft,
            null,
            0,
            null
        ));

        foreach ([
            ['description' => str_repeat('a', 256), 'quantity' => 1, 'unit_price' => 100],
            ['description' => 'Item', 'quantity' => 1000001, 'unit_price' => 100],
        ] as $payload) {
            try {
                $this->service->addItem($proposalId, $payload);
                $this->fail('Item inválido foi aceito');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
