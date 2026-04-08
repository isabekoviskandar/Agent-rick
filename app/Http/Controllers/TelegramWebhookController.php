<?php

namespace App\Http\Controllers;

use App\Ai\Agents\RickSanchez;
use App\Models\TelegramChat;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController
{
    /**
     * Handle incoming Telegram webhook updates.
     */
    public function __invoke(Request $request, TelegramService $telegram): JsonResponse
    {
        $update = $request->all();

        $message = $update['message'] ?? null;

        if (!$message || !isset($message['text'])) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'];

        $allowedUserId = config('services.telegram.allowed_user_id');
        if ($allowedUserId && (string) $chatId !== (string) $allowedUserId) {
            Log::warning('Unauthorized Telegram user attempted to use the bot', ['chat_id' => $chatId]);
            return response()->json(['ok' => true]);
        }

        $text = $message['text'];
        $username = $message['from']['username'] ?? null;
        $firstName = $message['from']['first_name'] ?? 'Unknown';

        // Handle /start command
        if ($text === '/start') {
            $this->handleStart($chatId, $firstName, $username, $telegram);

            return response()->json(['ok' => true]);
        }

        // Handle /reset command — start a new conversation
        if ($text === '/reset') {
            $this->handleReset($chatId, $firstName, $telegram);

            return response()->json(['ok' => true]);
        }

        // Process regular messages through Rick
        $this->processMessage($chatId, $text, $username, $firstName, $telegram);

        return response()->json(['ok' => true]);
    }

    /**
     * Handle the /start command.
     */
    private function handleStart(
        int $chatId,
        string $firstName,
        ?string $username,
        TelegramService $telegram,
    ): void {
        $chat = TelegramChat::updateOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'username' => $username,
                'first_name' => $firstName,
                'conversation_id' => null,
            ],
        );

        $rick = new RickSanchez;

        try {
            $response = $rick->forUser($chat)->prompt(
                "A new person just started chatting with you. Their name is {$firstName}. Greet them in your Rick Sanchez style. Be yourself — arrogant, funny, and slightly annoyed that someone is bothering you."
            );

            $chat->update(['conversation_id' => $response->conversationId]);

            $telegram->sendMessage($chatId, (string) $response, null);
        } catch (\Throwable $e) {
            Log::error('Rick agent error on /start', ['error' => $e->getMessage()]);
            $telegram->sendMessage(
                $chatId,
                "Look, *burp* something went wrong with my portal gun's communication array. Try again, Morty.",
                null,
            );
        }
    }

    /**
     * Handle the /reset command to start a fresh conversation.
     */
    private function handleReset(
        int $chatId,
        string $firstName,
        TelegramService $telegram,
    ): void {
        $chat = TelegramChat::where('telegram_chat_id', $chatId)->first();

        if ($chat) {
            $chat->update(['conversation_id' => null]);
        }

        $telegram->sendMessage(
            $chatId,
            "Fine, I wiped my memory of you. *burp* Not that there was much worth remembering. Send me something and we'll start fresh, {$firstName}.",
            null,
        );
    }

    /**
     * Process a regular message through the Rick agent.
     */
    private function processMessage(
        int $chatId,
        string $text,
        ?string $username,
        string $firstName,
        TelegramService $telegram,
    ): void {
        $chat = TelegramChat::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'username' => $username,
                'first_name' => $firstName,
            ],
        );

        // Show typing indicator
        $telegram->sendChatAction($chatId);

        $rick = new RickSanchez;

        try {
            if ($chat->conversation_id) {
                // Continue existing conversation
                $response = $rick
                    ->continue($chat->conversation_id, as: $chat)
                    ->prompt($text);
            } else {
                // Start a new conversation
                $response = $rick
                    ->forUser($chat)
                    ->prompt($text);

                $chat->update(['conversation_id' => $response->conversationId]);
            }

            $telegram->sendMessage($chatId, (string) $response, null);
        } catch (\Throwable $e) {
            Log::error('Rick agent error', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $telegram->sendMessage(
                $chatId,
                "Ugh, my brain temporarily glitched. Even geniuses have off *burp* moments. Hit me again.",
                null,
            );
        }
    }
}
