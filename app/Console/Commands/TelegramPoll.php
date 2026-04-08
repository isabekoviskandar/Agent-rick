<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramWebhookController;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramPoll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:poll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll for Telegram updates (local development mode)';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram): int
    {
        $this->info('🔬 Rick is online and judging your intelligence...');
        $this->info('Polling for Telegram updates. Press Ctrl+C to stop.');
        $this->newLine();

        // First, remove any existing webhook so polling works
        $telegram->removeWebhook();

        $offset = 0;

        while (true) {
            try {
                $updates = $telegram->getUpdates($offset);

                if (!$updates || !($updates['ok'] ?? false)) {
                    $this->error('Failed to get updates. Retrying in 5 seconds...');
                    sleep(5);

                    continue;
                }

                foreach ($updates['result'] ?? [] as $update) {
                    $offset = $update['update_id'] + 1;
                    $this->processUpdate($update, $telegram);
                }
            } catch (\Throwable $e) {
                $this->error("Error: {$e->getMessage()}");
                Log::error('Telegram polling error', ['error' => $e->getMessage()]);
                sleep(5);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Process a single Telegram update by dispatching it through the controller.
     *
     * @param  array<string, mixed>  $update
     */
    private function processUpdate(array $update, TelegramService $telegram): void
    {
        $message = $update['message'] ?? $update['channel_post'] ?? null;

        if (!$message) {
            return;
        }

        $from = $message['from']['first_name'] ?? $message['chat']['title'] ?? 'Unknown';
        $text = $message['text'] ?? '[non-text message]';
        $chatId = $message['chat']['id'] ?? 'unknown';

        $this->line("<fg=cyan>[{$from}]</> <fg=white>{$text}</>");

        // Create a fake request and dispatch through the controller
        $request = Request::create('/api/telegram/webhook', 'POST', $update);
        $controller = app(TelegramWebhookController::class);

        /** @var JsonResponse $response */
        $response = $controller($request, $telegram);

        $this->line("<fg=green>[Rick responded to chat {$chatId}]</>");
        $this->newLine();
    }
}
