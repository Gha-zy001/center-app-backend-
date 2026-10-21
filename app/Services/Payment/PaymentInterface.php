<?php
namespace App\Services\Payment;

/**
 * @deprecated Use App\Services\Payment\Contracts\PaymentGateway (see PaymentGatewayManager).
 */
interface PaymentInterface {
    public function pay(array $data): array;
    public function getPaymentStatus(string $invoiceId);
}