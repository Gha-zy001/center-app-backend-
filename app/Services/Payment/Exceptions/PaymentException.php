<?php

namespace App\Services\Payment\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Any provider failure, wrapped so callers catch one exception type regardless of gateway.
 */
class PaymentException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context  Provider error details (never includes API keys).
     */
    public function __construct(
        public readonly string $gateway,
        string $message,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct("[{$gateway}] {$message}", 0, $previous);
    }
}
