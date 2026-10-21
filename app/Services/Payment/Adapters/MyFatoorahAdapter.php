<?php

namespace App\Services\Payment\Adapters;

use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\Data\PaymentRequest;
use App\Services\Payment\Data\PaymentResult;
use App\Services\Payment\Data\PaymentSession;
use App\Services\Payment\Data\RefundResult;
use App\Services\Payment\Enums\PaymentStatus;
use App\Services\Payment\Exceptions\PaymentException;
use App\Services\Payment\Support\Currency;
use MyFatoorah\Library\MyFatoorah;
use Throwable;

/**
 * Adapts the MyFatoorah SDK to PaymentGateway.
 *
 * Uses the hosted payment-link flow (SendPayment + NotificationOption=LNK): MyFatoorah shows
 * its own page where the customer picks a payment method. MyFatoorah works in decimal amounts,
 * so minor units are converted on the way in and out.
 */
class MyFatoorahAdapter implements PaymentGateway
{
    public const NAME = 'myfatoorah';

    private const STATUSES = [
        'paid' => PaymentStatus::Paid,
        'pending' => PaymentStatus::Pending,
        'canceled' => PaymentStatus::Canceled,
        'cancelled' => PaymentStatus::Canceled,
        'failed' => PaymentStatus::Failed,
        'expired' => PaymentStatus::Expired,
    ];

    public function __construct(
        private readonly MyFatoorah $client,
        private readonly string $defaultCurrency = 'SAR',
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function createPayment(PaymentRequest $request): PaymentSession
    {
        $payload = array_filter([
            'NotificationOption' => 'LNK',
            'InvoiceValue' => (float) Currency::toDecimal($request->amount, $request->currency()),
            'DisplayCurrencyIso' => $request->currency(),
            'CustomerName' => $request->customerName ?? 'Customer',
            'CustomerEmail' => $request->customerEmail,
            'CustomerMobile' => $request->customerPhone,
            'CustomerReference' => $request->reference,
            'UserDefinedField' => $request->metadata ? json_encode($request->metadata) : null,
            'CallBackUrl' => $request->callbackUrl,
            'ErrorUrl' => $request->errorUrl(),
            'Language' => $request->language === 'ar' ? 'ar' : 'en',
            'InvoiceItems' => [[
                'ItemName' => $request->description,
                'Quantity' => 1,
                'UnitPrice' => (float) Currency::toDecimal($request->amount, $request->currency()),
            ]],
        ], fn ($value) => $value !== null);

        $data = $this->call('/v2/SendPayment', $payload, $request->reference);

        if (empty($data['InvoiceId']) || empty($data['InvoiceURL'])) {
            throw new PaymentException(self::NAME, 'Unexpected SendPayment response.', ['response' => $data]);
        }

        return new PaymentSession(self::NAME, (string) $data['InvoiceId'], $data['InvoiceURL'], $data);
    }

    public function getPayment(string $paymentId): PaymentResult
    {
        $data = $this->call('/v2/GetPaymentStatus', ['Key' => $paymentId, 'KeyType' => 'InvoiceId']);

        $currency = $this->paidCurrency($data) ?? $this->defaultCurrency;

        return new PaymentResult(
            gateway: self::NAME,
            paymentId: (string) ($data['InvoiceId'] ?? $paymentId),
            status: self::STATUSES[strtolower((string) ($data['InvoiceStatus'] ?? ''))] ?? PaymentStatus::Pending,
            amount: Currency::toMinor($data['InvoiceValue'] ?? 0, $currency),
            currency: $currency,
            reference: $data['CustomerReference'] ?? null,
            raw: $data,
        );
    }

    public function refund(string $paymentId, ?int $amount = null, ?string $reason = null): RefundResult
    {
        $payment = $this->getPayment($paymentId);

        if (! $payment->isPaid()) {
            throw new PaymentException(self::NAME, 'Only paid payments can be refunded.', ['status' => $payment->status->value]);
        }

        $amount ??= $payment->amount;

        $data = $this->call('/v2/MakeRefund', [
            'Key' => $paymentId,
            'KeyType' => 'InvoiceId',
            'RefundChargeOnCustomer' => false,
            'ServiceChargeOnCustomer' => false,
            'Amount' => (float) Currency::toDecimal($amount, $payment->currency),
            'Comment' => $reason ?? 'Refund',
        ]);

        return new RefundResult(
            gateway: self::NAME,
            paymentId: $paymentId,
            refundId: isset($data['RefundId']) ? (string) $data['RefundId'] : null,
            amount: $amount,
            raw: $data,
        );
    }

    /**
     * Calls the SDK and returns the response's "Data" as an array. The SDK throws on any
     * error; that is rethrown as PaymentException so callers handle one exception type.
     */
    private function call(string $path, array $payload, ?string $reference = null): array
    {
        try {
            $response = $this->client->callAPI($this->client->getApiURL() . $path, $payload, $reference, ltrim($path, '/'));
        } catch (Throwable $e) {
            throw new PaymentException(self::NAME, $e->getMessage(), ['endpoint' => $path], $e);
        }

        $response = json_decode(json_encode($response), true) ?? [];

        return $response['Data'] ?? [];
    }

    private function paidCurrency(array $data): ?string
    {
        foreach ($data['InvoiceTransactions'] ?? [] as $transaction) {
            if (! empty($transaction['PaidCurrency'])) {
                return strtoupper($transaction['PaidCurrency']);
            }
        }

        return null;
    }
}
