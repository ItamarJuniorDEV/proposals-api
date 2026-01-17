<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Contract;
use PHPUnit\Framework\TestCase;

class ContractTest extends TestCase
{
    public function testCreateContract(): void
    {
        $contract = new Contract(
            id: 'uuid-123',
            proposalId: 'proposal-456',
            totalAmount: 10000.00,
            approvedAt: '2025-01-15 14:30:00',
            createdAt: '2025-01-15 14:30:00'
        );

        $this->assertEquals('uuid-123', $contract->getId());
        $this->assertEquals('proposal-456', $contract->getProposalId());
        $this->assertEquals(10000.00, $contract->getTotalAmount());
        $this->assertEquals('2025-01-15 14:30:00', $contract->getApprovedAt());
    }

    public function testToArray(): void
    {
        $contract = new Contract(
            id: 'uuid-123',
            proposalId: 'proposal-456',
            totalAmount: 8500.50,
            approvedAt: '2025-01-15 14:30:00',
            createdAt: '2025-01-15 14:30:00'
        );

        $array = $contract->toArray();

        $this->assertEquals('uuid-123', $array['id']);
        $this->assertEquals('proposal-456', $array['proposal_id']);
        $this->assertEquals(8500.50, $array['total_amount']);
    }
}
