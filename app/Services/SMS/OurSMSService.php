<?php
namespace App\Services\SMS;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OurSMSService implements SMSInterface
{
    private string $bearerToken;
    private string $sender;

    public function __construct()
    {
        $this->bearerToken = (string) config('services.oursms.token');
        $this->sender = (string) config('services.oursms.sender');
    }

    public function send(string $to, string $message): array
    {
        try {
            $numbers = is_array($to) ? $to : [$to];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->bearerToken}",
                'Accept' => 'application/json',
            ])->post('https://api.oursms.com/msgs/sms', [
                'src' => $this->sender,
                'dests' => $numbers,
                'body' => $message,
                'priority' => 0,
                'delay' => 0,
                'validity' => 0,
                'maxParts' => 0,
                'dlr' => 0,
                'prevDups' => 0,
                'msgClass' => 'transactional',
            ]);

            if ($response->successful()) {
                // The body is not logged: it can contain one-time codes.
                Log::info('SMS sent', ['count' => count($numbers)]);
                return ['status' => true, 'message' => 'تم إرسال الرسالة بنجاح عبر OurSMS'];
            }

            Log::error("Failed to send SMS", [
                'numbers' => $numbers,
                'response' => $response->body()
            ]);
            return ['status' => false, 'message' => 'فشل إرسال الرسالة عبر OurSMS'];

        } catch (Exception $e) {
            Log::error("Exception while sending SMS", [
                'numbers' => is_array($to) ? $to : [$to],
                'error' => $e->getMessage()
            ]);
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}