<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;

    private string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    /**
     * Send a text message to a Telegram chat.
     *
     * @param  array{ok: bool, result?: array<string, mixed>}|null  $response
     *
     * @throws ConnectionException
     */
    public function sendMessage(int|string $chatId, string $text, ?string $parseMode = 'HTML'): ?array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/sendMessage", $payload);

        if ($response->failed()) {
            Log::error('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json();
    }

    /**
     * Send a chat action (e.g., "typing") to indicate activity.
     *
     * @throws ConnectionException
     */
    public function sendChatAction(int|string $chatId, string $action = 'typing'): void
    {
        Http::timeout(10)
            ->connectTimeout(5)
            ->post("{$this->baseUrl}/sendChatAction", [
                'chat_id' => $chatId,
                'action' => $action,
            ]);
    }

    /**
     * Set the webhook URL for the bot.
     *
     * @param  array{ok: bool, description?: string}|null  $response
     *
     * @throws ConnectionException
     */
    public function setWebhook(string $url): ?array
    {
        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/setWebhook", [
                'url' => $url,
                'allowed_updates' => ['message'],
            ]);

        return $response->json();
    }

    /**
     * Remove the webhook.
     *
     * @param  array{ok: bool, description?: string}|null  $response
     *
     * @throws ConnectionException
     */
    public function removeWebhook(): ?array
    {
        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/deleteWebhook");

        return $response->json();
    }

    /**
     * Get updates via long polling (for local development).
     *
     * @param  array{ok: bool, result?: list<array<string, mixed>>}|null  $response
     *
     * @throws ConnectionException
     */
    public function getUpdates(int $offset = 0, int $timeout = 30): ?array
    {
        $response = Http::timeout($timeout + 5)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/getUpdates", [
                'offset' => $offset,
                'timeout' => $timeout,
                'allowed_updates' => ['message'],
            ]);

        return $response->json();
    }
}
