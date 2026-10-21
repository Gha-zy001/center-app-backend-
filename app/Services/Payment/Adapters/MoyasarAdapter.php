<?php

namespace App\Services\Payment\Adapters;

use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\Data\PaymentRequest;
use App\Services\Payment\Data\PaymentResult;
use App\Services\Payment\Data\PaymentSession;
use App\Services\Payment\Data\RefundResult;
use App\Services\Payment\Enums\PaymentStatus;
use App\Services\Payment\Exceptions\PaymentException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Adapts the Moyasar REST API (https://docs.moyasar.com) to PaymentGateway.
 *
 * Uses Moyasar invoices: an invoice has a hosted checkout "url" where the customer pays by
 * card, Apple Pay or STC Pay. Moyasar already works in minor units (halalas), so amounts pass
 * through unchanged. Authentication is HTTP Basic with the secret key as the username.
 */
class MoyasarAdapter implements PaymentGateway
{
    public const NAME = 'moyasar';

    private const STATUSES = [
        'initiated' => PaymentStatus::Pending,
        'on_hold' => PaymentStatus::Pending,
        'paid' => PaymentStatus::Paid,
        'failed' => PaymentStatus::Failed,
        'refunded' => PaymentStatus::Refunded,
        'canceled' => PaymentStatus::Canceled,
        'voided' => PaymentStatus::Canceled,
        'expired' => PaymentStatus::Expired,
    ];

    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl = 'https://api.moyasar.com/v1',
        private readonly int $timeout = 20,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function createPayment(PaymentRequest $request): PaymentSession
    {
        $metadata = array_filter([
            ...$request->metadata,
            'reference' => $request->reference,
            'customer_name' => $request->customerName,
            'customer_email' => $request->customerEmail,
            'customer_phone' => $request->customerPhone,
        ], fn ($value) => $value !== null && $value !== '');

        $data = $this->send('post', '/invoices', array_filter([
            'amount' => $request->amount,
            'currency' => $request->currency(),
            'description' => $request->description,
            'success_url' => $request->callbackUrl,
            'back_url' => $request->errorUrl(),
            // Moyasar only accepts string values in metadata.
            'metadata' => $metadata ? array_map('strval', $metadata) : null,
        ], fn ($value) => $value !== null));

        if (empty($data['id']) || empty($data['url'])) {
            throw new PaymentException(self::NAME, 'Unexpected create invoice response.', ['response' => $data]);
        }

        return new PaymentSession(self::NAME, $data['id'], $data['url'], $data);
    }

    public function getPayment(string $paymentId): PaymentResult
    {
        $data = $this->send('get', '/invoices/' . rawurlencode($paymentId));

        return new PaymentResult(
            gateway: self::NAME,
            paymentId: $data['id'] ?? $paymentId,
            status: self::STATUSES[strtolower((string) ($data['status'] ?? ''))] ?? PaymentStatus::Pending,
            amount: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? '')),
            reference: $data['metadata']['reference'] ?? null,
            raw: $data,
        );
    }

    /**
     * Moyasar refunds the underlying payment, not the invoice, so the paid attempt is looked up first.
     */
    public function refund(string $paymentId, ?int $amount = null, ?string $reason = null): RefundResult
    {
        $invoice = $this->getPayment($paymentId);

        $paidAttempt = collect($invoice->raw['payments'] ?? [])->firstWhere('status', 'paid');

        if (! $invoice->isPaid() || ! $paidAttempt) {
            throw new PaymentException(self::NAME, 'Only paid payments can be refunded.', ['status' => $invoice->status->value]);
        }

        $data = $this->send(
            'post',
            '/payments/' . rawurlencode($paidAttempt['id']) . '/refund',
            $amount !== null ? ['amount' => $amount] : [],
        );

        return new RefundResult(
            gateway: self::NAME,
            paymentId: $paymentId,
            refundId: $data['id'] ?? $paidAttempt['id'],
            amount: $amount ?? (int) ($data['refunded'] ?? $invoice->amount),
            raw: $data,
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->retry(2, 200, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    private function send(string $method, string $path, array $payload = []): array
    {
        try {
            /** @var Response $response */
            $response = $method === 'get'
                ? $this->http()->get($path)
                : $this->http()->post($path, $payload);
        } catch (ConnectionException $e) {
            throw new PaymentException(self::NAME, 'Could not reach Moyasar.', ['endpoint' => $path], $e);
        }

        if ($response->failed()) {
            throw new PaymentException(
                self::NAME,
                (string) ($response->json('message') ?? "HTTP {$response->status()}"),
                ['endpoint' => $path, 'status' => $response->status(), 'errors' => $response->json('errors')],
            );
        }

        return $response->json() ?? [];
    }
}
