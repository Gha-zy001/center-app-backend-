<?php

namespace App\Services\Payment;

use App\Services\Payment\Adapters\MoyasarAdapter;
use App\Services\Payment\Adapters\MyFatoorahAdapter;
use App\Services\Payment\Contracts\PaymentGateway;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use MyFatoorah\Library\MyFatoorah;

/**
 * Resolves payment gateway adapters by name ("myfatoorah", "moyasar").
 *
 *   app(PaymentGatewayManager::class)->driver()           // the default from config
 *   app(PaymentGatewayManager::class)->driver('moyasar')  // a specific gateway
 *
 * To add a provider: write an adapter implementing PaymentGateway, add a create{Name}Driver()
 * method here and its credentials to config/services.php. Nothing else in the app changes.
 */
class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('services.payment.provider', MyFatoorahAdapter::NAME);
    }

    public function gateway(?string $name = null): PaymentGateway
    {
        return $this->driver($name);
    }

    protected function createMyfatoorahDriver(): PaymentGateway
    {
        $config = $this->config->get('services.myfatoorah', []);

        $this->requireKey(MyFatoorahAdapter::NAME, $config['api_key'] ?? null);

        return new MyFatoorahAdapter(
            new MyFatoorah([
                'apiKey' => $config['api_key'],
                'isTest' => (bool) ($config['test_mode'] ?? true),
                'vcCode' => $config['country_iso'] ?? 'SAU',
            ]),
            $config['currency'] ?? 'SAR',
        );
    }

    protected function createMoyasarDriver(): PaymentGateway
    {
        $config = $this->config->get('services.moyasar', []);

        $this->requireKey(MoyasarAdapter::NAME, $config['secret_key'] ?? null);

        return new MoyasarAdapter(
            $config['secret_key'],
            $config['base_url'] ?? 'https://api.moyasar.com/v1',
            (int) ($config['timeout'] ?? 20),
        );
    }

    private function requireKey(string $gateway, ?string $key): void
    {
        if (blank($key)) {
            throw new InvalidArgumentException("Payment gateway [{$gateway}] is not configured: missing API key.");
        }
    }
}
