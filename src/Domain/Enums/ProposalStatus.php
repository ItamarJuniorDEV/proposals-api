<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum ProposalStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function canSend(): bool
    {
        return $this === self::Draft;
    }

    public function canApprove(): bool
    {
        return $this === self::Sent;
    }

    public function canReject(): bool
    {
        return $this === self::Sent;
    }

    public function canRevise(): bool
    {
        return $this === self::Sent || $this === self::Rejected;
    }

    public function canEdit(): bool
    {
        return $this === self::Draft;
    }
}
