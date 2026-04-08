<?php

namespace Tests\Feature;

use App\Ai\Agents\RickSanchez;
use App\Models\TelegramChat;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_early_for_missing_message(): void
    {
        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 12345,
            // no message key
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_it_processes_regular_message_and_creates_chat(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendChatAction' => Http::response(['ok' => true]),
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true]),
        ]);

        RickSanchez::fake(['Wubba lubba dub dub']);

        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 12345,
            'message' => [
                'message_id' => 1,
                'chat' => [
                    'id' => 987654321,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'Morty',
                    'username' => 'morty_smith',
                ],
                'text' => 'Hello Rick',
            ],
        ]);

        $response->assertStatus(200);

        RickSanchez::assertPrompted('Hello Rick');

        $this->assertDatabaseHas('telegram_chats', [
            'telegram_chat_id' => 987654321,
            'first_name' => 'Morty',
            'username' => 'morty_smith',
        ]);

        // Assert conversation_id is not null after prompting
        $chat = TelegramChat::where('telegram_chat_id', 987654321)->first();
        $this->assertNotNull($chat->conversation_id);
    }

    public function test_it_handles_start_command(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true]),
        ]);

        RickSanchez::fake(['Greetings']);

        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 12345,
            'message' => [
                'message_id' => 1,
                'chat' => [
                    'id' => 111222333,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 111222333,
                    'is_bot' => false,
                    'first_name' => 'Summer',
                ],
                'text' => '/start',
            ],
        ]);

        $response->assertStatus(200);

        // Should be prompted with the intro prompt
        RickSanchez::assertPrompted(function ($prompt) {
            return $prompt->contains('Summer') && $prompt->contains('Greet');
        });

        $this->assertDatabaseHas('telegram_chats', [
            'telegram_chat_id' => 111222333,
            'first_name' => 'Summer',
        ]);
    }

    public function test_it_handles_reset_command(): void
    {
        // First create a chat with an active conversation ID
        TelegramChat::create([
            'telegram_chat_id' => 444555666,
            'first_name' => 'Jerry',
            'conversation_id' => 'conv_123',
        ]);

        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true]),
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 12345,
            'message' => [
                'message_id' => 1,
                'chat' => [
                    'id' => 444555666,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 444555666,
                    'is_bot' => false,
                    'first_name' => 'Jerry',
                ],
                'text' => '/reset',
            ],
        ]);

        $response->assertStatus(200);

        // Agent should not be prompted for a reset command
        RickSanchez::assertNeverPrompted();

        // Conservation ID should be nullified
        $chat = TelegramChat::where('telegram_chat_id', 444555666)->first();
        $this->assertNull($chat->conversation_id);
    }
}
