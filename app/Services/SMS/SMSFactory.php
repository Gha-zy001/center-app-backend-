<?php
namespace App\Services\SMS;

use Exception;

class SMSFactory {
    public static function make(?string $provider = null): SMSInterface {
        $provider = $provider ?? config('services.sms.provider', 'twilio');

        return match($provider) {
            'twilio' => new TwilioService(),
            'oursms' => new OurSMSService(),
            default => throw new Exception("مزود SMS غير مدعوم: $provider")
        };
    }
}