<?php

namespace App\Services\Payment\Data;

/**
 * A started checkout: send the customer to $url and store $paymentId with your order.
 */
final class PaymentSession
{
    /**
     * @param  array<string, mixed>  $raw  The provider's response, for logging and debugging.
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $paymentId,
        public readonly string $url,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'paymentId' => $this->paymentId,
            'url' => $this->url,
        ];
    }
}
