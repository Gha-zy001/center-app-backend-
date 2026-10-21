<?php
namespace App\Services\Payment;

use MyFatoorah\Library\MyFatoorah;

/**
 * @deprecated Use App\Services\Payment\Adapters\MyFatoorahAdapter through PaymentGatewayManager.
 */
class MyFatoorahService implements PaymentInterface
{
    protected MyFatoorah $myfatoorah;

    public function __construct()
    {
        $config = [
            'apiKey' => config('services.myfatoorah.api_key'),
            'isTest' => (bool) config('services.myfatoorah.test_mode', true),
            'vcCode' => config('services.myfatoorah.country_iso', 'SAU'),
        ];

        $this->myfatoorah = new MyFatoorah($config);
    }

    public function pay(array $data): array
    {
        $payload = [
            'NotificationOption' => 'LNK',
            'InvoiceValue' => $data['amount'],
            'CustomerName' => $data['customerName'],
            'CustomerEmail' => $data['customerEmail'],
            'PaymentMethodId' => $data['paymentMethodId'] ?? 11,
            'CallBackUrl' => $data['callbackUrl'],
            'ErrorUrl' => $data['errorUrl'],
            'Language' => 'en',
            'DisplayCurrencyIso' => config('services.myfatoorah.currency', 'SAR'),
        ];

        $response = $this->myfatoorah->callAPI(
            $this->myfatoorah->getApiURL() . '/v2/executePayment',
            $payload
        );

        return ['status' => true, 'message' => 'Payment initiated', 'data' => $response];
    }

    public function getPaymentStatus(string $invoiceId)
    {
        return $this->myfatoorah->callAPI(
            $this->myfatoorah->getApiURL() . '/v2/GetPaymentStatus',
            ['Key' => $invoiceId, 'KeyType' => 'InvoiceId']
        );
    }
}