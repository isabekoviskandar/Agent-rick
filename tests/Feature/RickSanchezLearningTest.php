<?php

namespace Tests\Feature;

use App\Ai\Agents\RickSanchez;
use App\Ai\Tools\RecordBehavioralNote;
use App\Ai\Tools\RecordVulnerability;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RickSanchezLearningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rick_agent_has_new_learning_tools(): void
    {
        $reflection = new \ReflectionClass(RickSanchez::class);
        $attributes = $reflection->getAttributes(\Laravel\Ai\Attributes\Tool::class);
        
        $toolClasses = array_map(fn($attr) => $attr->getArguments()[0], $attributes);

        $this->assertContains(RecordBehavioralNote::class, $toolClasses);
        $this->assertContains(RecordVulnerability::class, $toolClasses);
    }

    public function test_rick_agent_has_conditioning_instructions(): void
    {
        $rick = new RickSanchez;
        $instructions = (string) $rick->instructions();

        $this->assertStringContainsString('CONDITION THE USER', $instructions);
        $this->assertStringContainsString('RecordBehavioralNote', $instructions);
        $this->assertStringContainsString('RecordVulnerability', $instructions);
    }

    public function test_rick_agent_provider_list_is_expanded(): void
    {
        $rick = new RickSanchez;
        $providers = $rick->provider();

        $this->assertCount(16, $providers); // 15 OpenRouter slots + Lab::Gemini
        $this->assertArrayHasKey('openrouter15', $providers);
    }
}
