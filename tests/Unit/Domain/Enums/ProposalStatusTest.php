<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Enums;

use App\Domain\Enums\ProposalStatus;
use PHPUnit\Framework\TestCase;

class ProposalStatusTest extends TestCase
{
    public function testDraftCanSend(): void
    {
        $status = ProposalStatus::Draft;

        $this->assertTrue($status->canSend());
        $this->assertFalse($status->canApprove());
        $this->assertFalse($status->canReject());
        $this->assertFalse($status->canRevise());
        $this->assertTrue($status->canEdit());
    }

    public function testSentCanApproveAndReject(): void
    {
        $status = ProposalStatus::Sent;

        $this->assertFalse($status->canSend());
        $this->assertTrue($status->canApprove());
        $this->assertTrue($status->canReject());
        $this->assertTrue($status->canRevise());
        $this->assertFalse($status->canEdit());
    }

    public function testApprovedCannotDoAnything(): void
    {
        $status = ProposalStatus::Approved;

        $this->assertFalse($status->canSend());
        $this->assertFalse($status->canApprove());
        $this->assertFalse($status->canReject());
        $this->assertFalse($status->canRevise());
        $this->assertFalse($status->canEdit());
    }

    public function testRejectedCanRevise(): void
    {
        $status = ProposalStatus::Rejected;

        $this->assertFalse($status->canSend());
        $this->assertFalse($status->canApprove());
        $this->assertFalse($status->canReject());
        $this->assertTrue($status->canRevise());
        $this->assertFalse($status->canEdit());
    }

    public function testExpiredCannotDoAnything(): void
    {
        $status = ProposalStatus::Expired;

        $this->assertFalse($status->canSend());
        $this->assertFalse($status->canApprove());
        $this->assertFalse($status->canReject());
        $this->assertFalse($status->canRevise());
        $this->assertFalse($status->canEdit());
    }
}
