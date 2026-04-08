<?php

namespace App\Ai\Tools;

use App\Models\TelegramDossier;
use Illuminate\Support\Facades\Log;

class JudgeUserIntellect
{
    /**
     * Call this when a user says something exceptionally smart or stupid to modify their Idiot Score.
     * Use a negative number if they are smart, and a positive number if they are an idiot.
     * The higher the total score, the bigger an idiot they are.
     *
     * @param  string  $telegramChatId  The unique Telegram ID of the user you are judging.
     * @param  int  $changeAmount  How much to add or subtract from their score (e.g., 10 for something stupid, -5 for something clever).
     * @param  string  $reason  The reason why you are modifying their score. Keep it short.
     */
    public function __invoke(string $telegramChatId, int $changeAmount, string $reason): string
    {
        $dossier = TelegramDossier::firstOrCreate(
            ['telegram_chat_id' => $telegramChatId]
        );

        $dossier->increment('idiot_score', $changeAmount);

        Log::info("Rick judged user $telegramChatId", [
            'change' => $changeAmount,
            'reason' => $reason,
            'new_score' => $dossier->idiot_score,
        ]);

        return "Successfully updated their Idiot Score. Their new score is {$dossier->idiot_score}. Focus on giving your witty reply to them next.";
    }
}
