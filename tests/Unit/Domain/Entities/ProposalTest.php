<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Proposal;
use App\Domain\Enums\ProposalStatus;
use PHPUnit\Framework\TestCase;

class ProposalTest extends TestCase
{
    public function testCreateProposal(): void
    {
        $proposal = new Proposal(
            id: 'uuid-123',
            clientId: 'client-456',
            version: 1,
            parentId: null,
            status: ProposalStatus::Draft,
            validUntil: '2025-02-01',
            discountPercent: 10.0,
            notes: 'Proposta inicial'
        );

        $this->assertEquals('uuid-123', $proposal->getId());
        $this->assertEquals('client-456', $proposal->getClientId());
        $this->assertEquals(1, $proposal->getVersion());
        $this->assertNull($proposal->getParentId());
        $this->assertEquals(ProposalStatus::Draft, $proposal->getStatus());
        $this->assertEquals('2025-02-01', $proposal->getValidUntil());
        $this->assertEquals(10.0, $proposal->getDiscountPercent());
        $this->assertEquals('Proposta inicial', $proposal->getNotes());
    }

    public function testIsExpiredWithPastDate(): void
    {
        $proposal = new Proposal(
            id: 'uuid-123',
            clientId: 'client-456',
            version: 1,
            parentId: null,
            status: ProposalStatus::Sent,
            validUntil: '2020-01-01',
            discountPercent: 0,
            notes: null
        );

        $this->assertTrue($proposal->isExpired());
    }

    public function testIsExpiredWithFutureDate(): void
    {
        $proposal = new Proposal(
            id: 'uuid-123',
            clientId: 'client-456',
            version: 1,
            parentId: null,
            status: ProposalStatus::Sent,
            validUntil: '2030-12-31',
            discountPercent: 0,
            notes: null
        );

        $this->assertFalse($proposal->isExpired());
    }

    public function testIsExpiredWithNullDate(): void
    {
        $proposal = new Proposal(
            id: 'uuid-123',
            clientId: 'client-456',
            version: 1,
            parentId: null,
            status: ProposalStatus::Sent,
            validUntil: null,
            discountPercent: 0,
            notes: null
        );

        $this->assertFalse($proposal->isExpired());
    }

    public function testToArray(): void
    {
        $proposal = new Proposal(
            id: 'uuid-123',
            clientId: 'client-456',
            version: 2,
            parentId: 'parent-789',
            status: ProposalStatus::Sent,
            validUntil: '2025-02-01',
            discountPercent: 15.5,
            notes: 'Revisão'
        );

        $array = $proposal->toArray();

        $this->assertEquals('uuid-123', $array['id']);
        $this->assertEquals('client-456', $array['client_id']);
        $this->assertEquals(2, $array['version']);
        $this->assertEquals('parent-789', $array['parent_id']);
        $this->assertEquals('sent', $array['status']);
        $this->assertEquals(15.5, $array['discount_percent']);
    }
}
