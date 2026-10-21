<?php

namespace App\Services\Payment\Enums;

/**
 * Provider-neutral payment status. Each adapter maps its provider's statuses onto these.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    /**
     * No further change is expected without a new action (e.g. a refund).
     */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
