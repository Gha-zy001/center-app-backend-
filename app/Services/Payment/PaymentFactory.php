<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGateway;
use Exception;
use InvalidArgumentException;

/**
 * Static shortcut kept for existing callers. Prefer injecting PaymentGatewayManager or PaymentGateway.
 */
class PaymentFactory
{
    public static function make(?string $provider = null): PaymentGateway
    {
        $provider = $provider ?? config('services.payment.provider', 'myfatoorah');

        try {
            return app(PaymentGatewayManager::class)->driver($provider);
        } catch (InvalidArgumentException $e) {
            if (str_starts_with($e->getMessage(), 'Driver [')) {
                throw new Exception("Payment provider not supported: $provider", 0, $e);
            }

            throw $e;
        }
    }
}
