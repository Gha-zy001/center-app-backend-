<?php

namespace Modules\Classroom\app\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only place that talks to the Telegram Bot API. Sending never throws: a Telegram outage
 * must not fail a teacher's request, so errors are logged and reported as `false`.
 */
class TelegramClient
{
    /** @var string|null a specific bot's token; null means the shared platform bot */
    private ?string $token = null;

    /** A client speaking as another bot (a teacher's own bot, or the manager bot). */
    public function withToken(?string $token): static
    {
        $clone = clone $this;
        $clone->token = $token;

        return $clone;
    }

    public function isConfigured(): bool
    {
        return (bool) $this->token();
    }

    private function token(): ?string
    {
        return $this->token ?? config('services.telegram.token');
    }

    /** The bot's own profile: used to check a token and learn its username. */
    public function getMe(): ?array
    {
        $me = $this->call('getMe');

        return is_array($me) ? $me : null;
    }

    /** The token of a bot created through this (manager) bot. */
    public function getManagedBotToken(int $botUserId): ?string
    {
        $token = $this->call('getManagedBotToken', ['user_id' => $botUserId]);

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function botLink(): ?string
    {
        $username = config('services.telegram.bot_username');

        return $username ? "https://t.me/{$username}" : null;
    }

    public function sendMessage(string $chatId, string $text, array $extra = []): bool
    {
        return $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text] + $extra) !== null;
    }

    public function getUpdates(int $offset, int $timeout = 25): array
    {
        return $this->call('getUpdates', ['offset' => $offset, 'timeout' => $timeout], $timeout + 10) ?? [];
    }

    public function deleteWebhook(): void
    {
        $this->call('deleteWebhook');
    }

    public function setWebhook(string $url, ?string $secret = null, array $allowedUpdates = ['message']): bool
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret ?? config('services.telegram.webhook_secret'),
            'allowed_updates' => $allowedUpdates,
        ]) !== null;
    }

    private function call(string $method, array $payload = [], int $timeout = 8): mixed
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout($timeout)->post('https://api.telegram.org/bot'.$this->token().'/'.$method, $payload);

            if (! $response->successful() || ! $response->json('ok')) {
                // The URL holds the token, so only the method and Telegram's own description are logged.
                Log::warning("Telegram {$method} failed", ['status' => $response->status(), 'description' => $response->json('description')]);

                return null;
            }

            return $response->json('result') ?? true;
        } catch (Throwable $e) {
            Log::warning("Telegram {$method} errored", ['exception' => $e::class]);

            return null;
        }
    }
}
