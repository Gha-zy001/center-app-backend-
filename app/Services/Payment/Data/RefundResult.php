<?php

namespace App\Services\Payment\Data;

final class RefundResult
{
    /**
     * @param  int  $amount  Refunded amount in the smallest currency unit.
     * @param  array<string, mixed>  $raw  The provider's response, for logging and debugging.
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $paymentId,
        public readonly ?string $refundId,
        public readonly int $amount,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'paymentId' => $this->paymentId,
            'refundId' => $this->refundId,
            'amount' => $this->amount,
        ];
    }
}
