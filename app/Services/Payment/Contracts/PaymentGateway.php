<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\Data\PaymentRequest;
use App\Services\Payment\Data\PaymentResult;
use App\Services\Payment\Data\PaymentSession;
use App\Services\Payment\Data\RefundResult;
use App\Services\Payment\Exceptions\PaymentException;

/**
 * The single payment API the application codes against (the Adapter pattern's "target").
 *
 * Every provider (MyFatoorah, Moyasar, ...) gets an adapter that translates this interface to
 * its own API, so controllers and services never depend on a provider's payload shapes.
 *
 * Amounts are always integers in the currency's smallest unit (halalas for SAR, fils for KWD).
 */
interface PaymentGateway
{
    /**
     * The provider key used in config and stored with transactions, e.g. "moyasar".
     */
    public function name(): string;

    /**
     * Start a hosted checkout. Redirect the customer to the returned session's URL.
     *
     * @throws PaymentException
     */
    public function createPayment(PaymentRequest $request): PaymentSession;

    /**
     * Fetch the authoritative state of a payment from the provider.
     *
     * Always call this before fulfilling an order: redirect query strings can be forged.
     *
     * @throws PaymentException
     */
    public function getPayment(string $paymentId): PaymentResult;

    /**
     * Refund a paid payment. A null amount refunds the full amount.
     *
     * @throws PaymentException
     */
    public function refund(string $paymentId, ?int $amount = null, ?string $reason = null): RefundResult;
}
