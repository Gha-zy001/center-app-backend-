<?php

namespace App\Services\Payment\Data;

use App\Services\Payment\Enums\PaymentStatus;

/**
 * The provider's authoritative view of a payment, normalized.
 */
final class PaymentResult
{
    /**
     * @param  int  $amount  Smallest currency unit.
     * @param  array<string, mixed>  $raw  The provider's response, for logging and debugging.
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $paymentId,
        public readonly PaymentStatus $status,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $reference = null,
        public readonly array $raw = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->status->isPaid();
    }

    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'paymentId' => $this->paymentId,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference,
        ];
    }
}
