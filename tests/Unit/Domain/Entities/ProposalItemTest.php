<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\ProposalItem;
use PHPUnit\Framework\TestCase;

class ProposalItemTest extends TestCase
{
    public function testCreateItem(): void
    {
        $item = new ProposalItem(
            id: 'uuid-123',
            proposalId: 'proposal-456',
            description: 'Desenvolvimento de site',
            quantity: 1,
            unitPrice: 5000.00
        );

        $this->assertEquals('uuid-123', $item->getId());
        $this->assertEquals('proposal-456', $item->getProposalId());
        $this->assertEquals('Desenvolvimento de site', $item->getDescription());
        $this->assertEquals(1, $item->getQuantity());
        $this->assertEquals(5000.00, $item->getUnitPrice());
    }

    public function testGetSubtotal(): void
    {
        $item = new ProposalItem(
            id: 'uuid-123',
            proposalId: 'proposal-456',
            description: 'Hora de consultoria',
            quantity: 10,
            unitPrice: 150.00
        );

        $this->assertEquals(1500.00, $item->getSubtotal());
    }

    public function testGetSubtotalWithDecimal(): void
    {
        $item = new ProposalItem(
            id: 'uuid-123',
            proposalId: 'proposal-456',
            description: 'Serviço',
            quantity: 3,
            unitPrice: 99.99
        );

        $this->assertEquals(299.97, $item->getSubtotal());
    }

    public function testToArrayIncludesSubtotal(): void
    {
        $item = new ProposalItem(
            id: 'uuid-123',
            proposalId: 'proposal-456',
            description: 'Item teste',
            quantity: 2,
            unitPrice: 100.00
        );

        $array = $item->toArray();

        $this->assertEquals(200.00, $array['subtotal']);
        $this->assertEquals('Item teste', $array['description']);
        $this->assertEquals(2, $array['quantity']);
        $this->assertEquals(100.00, $array['unit_price']);
    }
}
