<?php

namespace Tests\Feature;

use App\Services\Payment\Adapters\MoyasarAdapter;
use App\Services\Payment\Adapters\MyFatoorahAdapter;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\Data\PaymentRequest;
use App\Services\Payment\Enums\PaymentStatus;
use App\Services\Payment\Exceptions\PaymentException;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\Support\Currency;
use Exception;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Mockery;
use MyFatoorah\Library\MyFatoorah;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    private function request(int $amount = 10000, string $currency = 'SAR'): PaymentRequest
    {
        return new PaymentRequest(
            amount: $amount,
            currency: $currency,
            description: 'Order #42',
            callbackUrl: 'https://shop.test/pay/success',
            errorUrl: 'https://shop.test/pay/failed',
            customerName: 'Sara',
            customerEmail: 'sara@example.com',
            reference: 'order-42',
            metadata: ['cart' => 'c-9'],
        );
    }

    // ------------------------------------------------------------ Currency

    public function test_currency_conversion_respects_decimal_places(): void
    {
        $this->assertSame('100.00', Currency::toDecimal(10000, 'SAR'));
        $this->assertSame('12.345', Currency::toDecimal(12345, 'KWD'));
        $this->assertSame(10050, Currency::toMinor('100.50', 'SAR'));
        $this->assertSame(12345, Currency::toMinor(12.345, 'KWD'));
        $this->assertSame(1999, Currency::toMinor(19.99, 'SAR'), 'no float truncation (19.99 * 100 = 1998.999...)');
    }

    public function test_payment_request_rejects_invalid_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaymentRequest(amount: 0, currency: 'SAR', description: 'x', callbackUrl: 'https://a.test');
    }

    // ------------------------------------------------------------- Moyasar

    private function moyasar(): MoyasarAdapter
    {
        return new MoyasarAdapter('sk_test_secret', 'https://api.moyasar.com/v1');
    }

    public function test_moyasar_creates_an_invoice_in_halalas_with_basic_auth(): void
    {
        Http::fake([
            'api.moyasar.com/v1/invoices' => Http::response([
                'id' => 'inv_123',
                'status' => 'initiated',
                'amount' => 10000,
                'currency' => 'SAR',
                'url' => 'https://checkout.moyasar.com/invoices/inv_123',
            ], 201),
        ]);

        $session = $this->moyasar()->createPayment($this->request());

        $this->assertSame('moyasar', $session->gateway);
        $this->assertSame('inv_123', $session->paymentId);
        $this->assertSame('https://checkout.moyasar.com/invoices/inv_123', $session->url);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.moyasar.com/v1/invoices'
                && $request->header('Authorization')[0] === 'Basic ' . base64_encode('sk_test_secret:')
                && $request['amount'] === 10000
                && $request['currency'] === 'SAR'
                && $request['description'] === 'Order #42'
                && $request['success_url'] === 'https://shop.test/pay/success'
                && $request['back_url'] === 'https://shop.test/pay/failed'
                && $request['metadata'] === ['cart' => 'c-9', 'reference' => 'order-42', 'customer_name' => 'Sara', 'customer_email' => 'sara@example.com'];
        });
    }

    public function test_moyasar_maps_invoice_statuses(): void
    {
        $cases = [
            'initiated' => PaymentStatus::Pending,
            'paid' => PaymentStatus::Paid,
            'failed' => PaymentStatus::Failed,
            'expired' => PaymentStatus::Expired,
            'canceled' => PaymentStatus::Canceled,
            'refunded' => PaymentStatus::Refunded,
        ];

        // The invoice id carries the status to return, so one fake serves every case.
        Http::fake([
            'api.moyasar.com/v1/invoices/*' => fn (Request $request) => Http::response([
                'id' => basename($request->url()),
                'status' => substr(basename($request->url()), strlen('inv_')),
                'amount' => 5000,
                'currency' => 'SAR',
                'metadata' => ['reference' => 'order-7'],
            ]),
        ]);

        foreach ($cases as $moyasarStatus => $expected) {
            $result = $this->moyasar()->getPayment("inv_{$moyasarStatus}");

            $this->assertSame($expected, $result->status, "moyasar status {$moyasarStatus}");
            $this->assertSame(5000, $result->amount);
            $this->assertSame('SAR', $result->currency);
            $this->assertSame('order-7', $result->reference);
        }
    }

    public function test_moyasar_refunds_the_paid_attempt_of_an_invoice(): void
    {
        Http::fake([
            'api.moyasar.com/v1/invoices/inv_1' => Http::response([
                'id' => 'inv_1', 'status' => 'paid', 'amount' => 10000, 'currency' => 'SAR',
                'payments' => [
                    ['id' => 'pay_failed', 'status' => 'failed'],
                    ['id' => 'pay_ok', 'status' => 'paid'],
                ],
            ]),
            'api.moyasar.com/v1/payments/pay_ok/refund' => Http::response(['id' => 'pay_ok', 'status' => 'refunded', 'refunded' => 2500]),
        ]);

        $refund = $this->moyasar()->refund('inv_1', 2500, 'Damaged item');

        $this->assertSame(2500, $refund->amount);
        $this->assertSame('pay_ok', $refund->refundId);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.moyasar.com/v1/payments/pay_ok/refund' && $r['amount'] === 2500);
    }

    public function test_moyasar_refuses_to_refund_an_unpaid_invoice(): void
    {
        Http::fake(['api.moyasar.com/v1/invoices/*' => Http::response(['id' => 'inv_1', 'status' => 'initiated', 'amount' => 100, 'currency' => 'SAR'])]);

        $this->expectException(PaymentException::class);

        $this->moyasar()->refund('inv_1');
    }

    public function test_moyasar_errors_become_payment_exceptions_with_the_provider_message(): void
    {
        Http::fake(['api.moyasar.com/*' => Http::response(['type' => 'invalid_request_error', 'message' => 'Amount must be at least 100'], 400)]);

        try {
            $this->moyasar()->createPayment($this->request(50));
            $this->fail('expected PaymentException');
        } catch (PaymentException $e) {
            $this->assertSame('moyasar', $e->gateway);
            $this->assertStringContainsString('Amount must be at least 100', $e->getMessage());
            $this->assertSame(400, $e->context['status']);
            $this->assertStringNotContainsString('sk_test_secret', json_encode($e->context), 'never leak the key');
        }
    }

    // ---------------------------------------------------------- MyFatoorah

    private function myFatoorah(Mockery\MockInterface $client, string $currency = 'SAR'): MyFatoorahAdapter
    {
        $client->shouldReceive('getApiURL')->andReturn('https://apitest.myfatoorah.com');

        return new MyFatoorahAdapter($client, $currency);
    }

    private function mfResponse(array $data): object
    {
        return json_decode(json_encode(['IsSuccess' => true, 'Data' => $data]));
    }

    public function test_myfatoorah_sends_a_payment_link_in_decimal_amounts(): void
    {
        $client = Mockery::mock(MyFatoorah::class);
        $client->shouldReceive('callAPI')->once()->withArgs(function ($url, $payload, $reference) {
            return $url === 'https://apitest.myfatoorah.com/v2/SendPayment'
                && $payload['NotificationOption'] === 'LNK'
                && $payload['InvoiceValue'] === 100.0
                && $payload['DisplayCurrencyIso'] === 'SAR'
                && $payload['CustomerReference'] === 'order-42'
                && $payload['CallBackUrl'] === 'https://shop.test/pay/success'
                && $payload['ErrorUrl'] === 'https://shop.test/pay/failed'
                && json_decode($payload['UserDefinedField'], true) === ['cart' => 'c-9']
                && $reference === 'order-42';
        })->andReturn($this->mfResponse(['InvoiceId' => 555, 'InvoiceURL' => 'https://demo.myfatoorah.com/pay/555']));

        $session = $this->myFatoorah($client)->createPayment($this->request());

        $this->assertSame('myfatoorah', $session->gateway);
        $this->assertSame('555', $session->paymentId);
        $this->assertSame('https://demo.myfatoorah.com/pay/555', $session->url);
    }

    public function test_myfatoorah_uses_three_decimals_for_kwd(): void
    {
        $client = Mockery::mock(MyFatoorah::class);
        $client->shouldReceive('callAPI')->once()
            ->withArgs(fn ($url, $payload) => $payload['InvoiceValue'] === 12.345 && $payload['DisplayCurrencyIso'] === 'KWD')
            ->andReturn($this->mfResponse(['InvoiceId' => 1, 'InvoiceURL' => 'https://x.test']));

        $this->myFatoorah($client, 'KWD')->createPayment($this->request(12345, 'KWD'));
    }

    public function test_myfatoorah_maps_status_and_amount_back_to_minor_units(): void
    {
        $client = Mockery::mock(MyFatoorah::class);
        $client->shouldReceive('callAPI')
            ->withArgs(fn ($url, $payload) => str_ends_with($url, '/v2/GetPaymentStatus') && $payload === ['Key' => '555', 'KeyType' => 'InvoiceId'])
            ->andReturn($this->mfResponse([
                'InvoiceId' => 555, 'InvoiceStatus' => 'Paid', 'InvoiceValue' => 100.5, 'CustomerReference' => 'order-42',
                'InvoiceTransactions' => [['PaidCurrency' => 'SAR']],
            ]));

        $result = $this->myFatoorah($client)->getPayment('555');

        $this->assertSame(PaymentStatus::Paid, $result->status);
        $this->assertTrue($result->isPaid());
        $this->assertSame(10050, $result->amount);
        $this->assertSame('SAR', $result->currency);
        $this->assertSame('order-42', $result->reference);
    }

    public function test_myfatoorah_full_refund_uses_the_paid_amount(): void
    {
        $client = Mockery::mock(MyFatoorah::class);
        $client->shouldReceive('callAPI')
            ->withArgs(fn ($url) => str_ends_with($url, '/v2/GetPaymentStatus'))
            ->andReturn($this->mfResponse(['InvoiceId' => 555, 'InvoiceStatus' => 'Paid', 'InvoiceValue' => 100]));
        $client->shouldReceive('callAPI')->once()
            ->withArgs(fn ($url, $payload) => str_ends_with($url, '/v2/MakeRefund') && $payload['Amount'] === 100.0 && $payload['Key'] === '555')
            ->andReturn($this->mfResponse(['RefundId' => 77, 'Amount' => 100]));

        $refund = $this->myFatoorah($client)->refund('555');

        $this->assertSame('77', $refund->refundId);
        $this->assertSame(10000, $refund->amount);
    }

    public function test_myfatoorah_sdk_errors_become_payment_exceptions(): void
    {
        $client = Mockery::mock(MyFatoorah::class);
        $client->shouldReceive('callAPI')->andThrow(new Exception('Invalid token'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('[myfatoorah] Invalid token');

        $this->myFatoorah($client)->getPayment('1');
    }

    // ------------------------------------------------------------- Manager

    public function test_manager_resolves_each_gateway_by_name_and_the_default_from_config(): void
    {
        config([
            'services.payment.provider' => 'moyasar',
            'services.moyasar.secret_key' => 'sk_test_x',
            'services.myfatoorah.api_key' => 'mf_test_x',
        ]);

        $manager = new PaymentGatewayManager($this->app);

        $this->assertInstanceOf(MoyasarAdapter::class, $manager->driver());
        $this->assertInstanceOf(MoyasarAdapter::class, $manager->gateway('moyasar'));
        $this->assertInstanceOf(MyFatoorahAdapter::class, $manager->gateway('myfatoorah'));
        $this->assertInstanceOf(PaymentGateway::class, $manager->gateway('myfatoorah'));
    }

    public function test_the_payment_gateway_contract_resolves_to_the_default_adapter(): void
    {
        config(['services.payment.provider' => 'moyasar', 'services.moyasar.secret_key' => 'sk_test_x']);
        $this->app->forgetInstance(PaymentGatewayManager::class);

        $this->assertInstanceOf(MoyasarAdapter::class, $this->app->make(PaymentGateway::class));
    }

    public function test_manager_fails_clearly_for_missing_keys_and_unknown_gateways(): void
    {
        config(['services.moyasar.secret_key' => null]);
        $manager = new PaymentGatewayManager($this->app);

        try {
            $manager->driver('moyasar');
            $this->fail('expected missing key error');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('missing API key', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $manager->driver('paypal');
    }
}
