<?php

namespace App\Http\Controllers;

use App\Ai\Agents\RickSanchez;
use App\Models\TelegramChat;
use App\Models\TelegramDossier;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController
{
    /**
     * Handle incoming Telegram webhook updates.
     */
    public function __invoke(Request $request, TelegramService $telegram): JsonResponse
    {
        $update = $request->all();

        $isChannelPost = isset($update['channel_post']);
        $message = $update['message'] ?? $update['channel_post'] ?? null;

        if (! $message || ! isset($message['text'])) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'];

        // Authorization Check
        $allowedIdsString = config('services.telegram.allowed_ids');
        if ($allowedIdsString) {
            $allowedIds = array_map('trim', explode(',', $allowedIdsString));
            if (! in_array((string) $chatId, $allowedIds, true)) {
                Log::warning('Unauthorized Telegram group/user/channel attempted to use the bot', ['chat_id' => $chatId]);

                return response()->json(['ok' => true]);
            }
        }

        $text = $message['text'];
        $username = $message['from']['username'] ?? $message['chat']['username'] ?? null;
        $firstName = $message['from']['first_name'] ?? $message['chat']['title'] ?? 'Unknown';
        $messageId = $message['message_id'];

        // Extract bot username if possible to check for mentions
        $botUsername = config('services.telegram.bot_username', 'AgentRickBot'); // Replace or pull dynamically

        // Handle /start command
        if ($text === '/start') {
            $this->handleStart($chatId, $firstName, $username, $telegram);

            return response()->json(['ok' => true]);
        }

        // Handle /reset command
        if ($text === '/reset') {
            $this->handleReset($chatId, $firstName, $telegram);

            return response()->json(['ok' => true]);
        }

        // Handle /rickstats command
        if (stripos($text, '/rickstats') === 0) {
            $this->handleStats($chatId, $telegram);

            return response()->json(['ok' => true]);
        }

        // Group Chat Logic — Decide if we should reply
        $shouldReply = false;
        $chatType = $message['chat']['type'] ?? 'private';

        if ($chatType === 'private') {
            $shouldReply = true;
        } elseif ($chatType === 'channel') {
            // Channel Strategy: ONLY reply if explicitly commanded
            if (stripos($text, '/rick') !== false || stripos($text, '@'.$botUsername) !== false) {
                $shouldReply = true;
            }
        } else {
            // Group/Discussion Strategy
            // 1. Check if mentioned or commanded
            if (stripos($text, '/rick') !== false || stripos($text, '@'.$botUsername) !== false || stripos($text, 'rick') !== false) {
                $shouldReply = true;
            }

            // 2. Check if a direct reply to the bot
            if (isset($message['reply_to_message']['from']['is_bot']) && $message['reply_to_message']['from']['is_bot']) {
                $shouldReply = true;
            }

            // 3. Check if first comment on a channel post (replying to an automatically forwarded message from the channel)
            if (isset($message['reply_to_message']['is_automatic_forward']) && $message['reply_to_message']['is_automatic_forward']) {
                $originalPostId = $message['reply_to_message']['message_id'];

                // Use cache to track if we've already replied to this post
                // We add it to cache, if it's true, it means it's the very first time!
                if (Cache::add("replied_channel_post_{$chatId}_{$originalPostId}", true, now()->addDays(7))) {
                    $shouldReply = true;
                }
            }

            // 4. Random Chance (10% by default)
            $chance = config('services.telegram.random_reply_chance', 10);
            if (! $shouldReply && rand(1, 100) <= $chance) {
                $shouldReply = true;
            }
        }

        if ($shouldReply) {
            $this->processMessage($chatId, $text, $username, $firstName, $chatType, $messageId, $telegram);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle the /start command.
     */
    private function handleStart(
        int|string $chatId,
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
        int|string $chatId,
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
        int|string $chatId,
        string $text,
        ?string $username,
        string $firstName,
        string $chatType,
        int $messageId,
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

        // Fetch user dossier for memory profiling
        $dossier = TelegramDossier::firstOrCreate(['telegram_chat_id' => $chatId]);
        $factsString = empty($dossier->known_facts) ? 'None' : implode('; ', $dossier->known_facts);
        $behaviorString = empty($dossier->behavioral_notes) ? 'None' : collect($dossier->behavioral_notes)->map(fn ($n) => $n['pattern'])->implode('; ');
        $vulnerabilityString = empty($dossier->vulnerability_notes) ? 'None' : collect($dossier->vulnerability_notes)->map(fn ($v) => $v['vulnerability'])->implode('; ');

        $contextPrefix = "[DOSSIER FOR {$firstName}]\n";
        $contextPrefix .= "- Known Facts: {$factsString}\n";
        $contextPrefix .= "- Behavioral Patterns: {$behaviorString}\n";
        $contextPrefix .= "- Psychological Vulnerabilities: {$vulnerabilityString}\n";
        $contextPrefix .= "- Idiot Score: {$dossier->idiot_score} (Higher means they're acting like a Jerry)\n";

        if ($chatType !== 'private') {
            $contextPrefix .= "[SOCIAL CONTEXT: Group/Channel message from {$firstName}]\n";
        }

        $promptText = $contextPrefix."\nUSER MESSAGE: ".$text;

        try {
            if ($chat->conversation_id) {
                // Continue existing conversation
                $response = $rick
                    ->continue($chat->conversation_id, as: $chat)
                    ->prompt($promptText);
            } else {
                // Start a new conversation
                $response = $rick
                    ->forUser($chat)
                    ->prompt($promptText);

                $chat->update(['conversation_id' => $response->conversationId]);
            }

            // In groups, reply directly to the message
            $replyToId = ($chatType === 'private') ? null : $messageId;
            $telegram->sendMessage($chatId, (string) $response, null, $replyToId);

        } catch (\Throwable $e) {
            Log::error('Rick agent error', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $replyToId = ($chatType === 'private') ? null : $messageId;
            $telegram->sendMessage(
                $chatId,
                'Ugh, my brain temporarily glitched. Even geniuses have off *burp* moments. Hit me again.',
                null,
                $replyToId
            );
        }
    }

    /**
     * Handle the /rickstats command.
     */
    private function handleStats(int|string $chatId, TelegramService $telegram): void
    {
        $dossier = TelegramDossier::firstOrCreate(['telegram_chat_id' => $chatId]);
        $facts = empty($dossier->known_facts) ? "I don't know anything about you yet." : implode("\n- ", $dossier->known_facts);

        $message = "📊 *IDIOT PROFILE* 📊\n\n";
        $message .= "Your Idiot Score: *{$dossier->idiot_score}*\n\n";
        $message .= "Things I know about you:\n- {$facts}\n\n";
        $message .= '_Say something dumb and watch your score go up._';

        $telegram->sendMessage($chatId, $message, 'Markdown');
    }
}
