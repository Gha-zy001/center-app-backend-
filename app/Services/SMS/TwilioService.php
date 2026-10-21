<?php
namespace App\Services\SMS;

use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioService implements SMSInterface
{
    protected Client $client;
    protected string $from;

    public function __construct()
    {
        $this->client = new Client(config('services.twilio.sid'), config('services.twilio.auth_token'));
        $this->from = (string) config('services.twilio.phone');
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function send(string $to, string $message): array
    {
        try {
            $this->client->messages->create($to, [
                'from' => $this->from,
                'body' => $message
            ]);

            return ['status' => true, 'message' => 'تم إرسال الرسالة بنجاح عبر Twilio'];
        } catch (Exception $e) {
            Log::error('Twilio SMS Error: ' . $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}