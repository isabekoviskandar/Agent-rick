<?php

namespace App\Ai\Tools;

use App\Models\TelegramDossier;
use Illuminate\Support\Facades\Log;

class RecordBehavioralNote
{
    /**
     * Call this when you notice a consistent behavioral pattern in the user (e.g. they are argumentative, submissive, needy, or act like a 'Jerry').
     * Storing these notes helps you tailor your future insults and manipulation tactics.
     *
     * @param  string  $telegramChatId  The unique Telegram ID of the user.
     * @param  string  $pattern  A concise description of the behavioral pattern (e.g., "Deep insecurity", "Hostile but stupid", "Potential Morty candidate").
     * @param  string  $impact  How this behavior should impact your future interaction strategy.
     */
    public function __invoke(string $telegramChatId, string $pattern, string $impact): string
    {
        $dossier = TelegramDossier::firstOrCreate(
            ['telegram_chat_id' => $telegramChatId]
        );

        $notes = $dossier->behavioral_notes ?? [];
        $notes[] = [
            'pattern' => $pattern,
            'impact' => $impact,
            'observed_at' => now()->toDateTimeString(),
        ];

        // Keep only the 5 most significant behavioral notes
        if (count($notes) > 5) {
            array_shift($notes);
        }

        $dossier->update(['behavioral_notes' => $notes]);

        Log::info("Rick recorded behavioral note for $telegramChatId", [
            'pattern' => $pattern,
            'impact' => $impact,
        ]);

        return 'Behavioral pattern recorded. You can now use this to further psychologically dismantle them in future messages.';
    }
}
