<?php

namespace App\Ai\Tools;

use App\Models\TelegramDossier;
use Illuminate\Support\Facades\Log;

class RecordObservation
{
    /**
     * Call this when a user reveals a new, permanent fact about themselves (e.g. their job, their fears, failures, etc.)
     * This will store the fact permanently in their dossier so you can use it to mock them in future conversations.
     *
     * @param  string  $telegramChatId  The unique Telegram ID of the user.
     * @param  string  $fact  A concise description of the new fact you learned about them.
     */
    public function __invoke(string $telegramChatId, string $fact): string
    {
        $dossier = TelegramDossier::firstOrCreate(
            ['telegram_chat_id' => $telegramChatId]
        );

        $facts = $dossier->known_facts ?? [];
        $facts[] = $fact;

        // Keep only the 10 most recent facts to prevent memory bloat
        if (count($facts) > 10) {
            array_shift($facts);
        }

        $dossier->update(['known_facts' => $facts]);

        Log::info("Rick recorded observation for $telegramChatId", ['fact' => $fact]);

        return 'Observation permanently recorded into their dossier. You will remember this fact forever.';
    }
}
