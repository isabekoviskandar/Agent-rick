<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook {url : The public URL for the webhook}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the Telegram bot webhook URL';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram): int
    {
        $url = $this->argument('url');

        $this->info("Setting webhook to: {$url}");

        $result = $telegram->setWebhook($url);

        if ($result && ($result['ok'] ?? false)) {
            $this->info('✅ Webhook set successfully!');
            $this->info($result['description'] ?? '');

            return self::SUCCESS;
        }

        $this->error('❌ Failed to set webhook.');
        $this->error($result['description'] ?? 'Unknown error');

        return self::FAILURE;
    }
}
