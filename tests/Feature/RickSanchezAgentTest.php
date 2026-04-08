<?php

namespace Tests\Feature;

use App\Ai\Agents\RickSanchez;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RickSanchezAgentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rick_agent_can_be_prompted(): void
    {
        RickSanchez::fake(['Wubba lubba dub dub! What do you want, Morty?']);

        $response = (new RickSanchez)->prompt('Hello Rick!');

        $this->assertNotEmpty((string) $response);

        RickSanchez::assertPrompted('Hello Rick!');
    }

    public function test_rick_agent_returns_response_text(): void
    {
        $expectedResponse = "Listen, I'm the smartest man in the universe. What do you *burp* want?";

        RickSanchez::fake([$expectedResponse]);

        $response = (new RickSanchez)->prompt('Who are you?');

        $this->assertEquals($expectedResponse, (string) $response);
    }

    public function test_rick_agent_instructions_are_not_empty(): void
    {
        $rick = new RickSanchez;

        $instructions = $rick->instructions();

        $this->assertNotEmpty($instructions);
        $this->assertStringContainsString('Rick Sanchez', (string) $instructions);
        $this->assertStringContainsString('C-137', (string) $instructions);
    }

    public function test_rick_agent_is_never_prompted_when_no_calls_made(): void
    {
        RickSanchez::fake();

        RickSanchez::assertNeverPrompted();
    }

    public function test_rick_agent_prevents_stray_prompts(): void
    {
        RickSanchez::fake(['Some response'])->preventStrayPrompts();

        $response = (new RickSanchez)->prompt('Test');

        $this->assertNotEmpty((string) $response);
    }
}
