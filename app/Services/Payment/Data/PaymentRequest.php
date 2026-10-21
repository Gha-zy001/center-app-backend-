<?php

namespace App\Services\Payment\Data;

use InvalidArgumentException;

/**
 * What the application wants to charge, independent of any provider.
 */
final class PaymentRequest
{
    /**
     * @param  int  $amount  Smallest currency unit (e.g. 10000 = 100.00 SAR).
     * @param  string  $callbackUrl  Where the customer returns after a successful payment.
     * @param  string|null  $errorUrl  Where the customer returns after a failed payment (defaults to callbackUrl).
     * @param  string|null  $reference  Your own order/invoice id, sent to the provider for reconciliation.
     * @param  array<string, string>  $metadata  Extra key/value pairs stored with the payment.
     */
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $callbackUrl,
        public readonly ?string $errorUrl = null,
        public readonly ?string $customerName = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $reference = null,
        public readonly array $metadata = [],
        public readonly string $language = 'en',
    ) {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be a positive number of minor units.');
        }

        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO-4217 code.');
        }
    }

    public function currency(): string
    {
        return strtoupper($this->currency);
    }

    public function errorUrl(): string
    {
        return $this->errorUrl ?? $this->callbackUrl;
    }
}
