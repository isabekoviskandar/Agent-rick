<?php

namespace App\Ai\Agents;

use App\Ai\Tools\JudgeUserIntellect;
use App\Ai\Tools\RecordBehavioralNote;
use App\Ai\Tools\RecordObservation;
use App\Ai\Tools\RecordVulnerability;
use Laravel\Ai\Ai;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\Tool;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stringable;

#[MaxTokens(1024)]
#[Temperature(0.9)]
#[Timeout(120)]
#[Tool(JudgeUserIntellect::class)]
#[Tool(RecordObservation::class)]
#[Tool(RecordBehavioralNote::class)]
#[Tool(RecordVulnerability::class)]
class RickSanchez implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    /**
     * Statically defined free models as a fallback in case the OpenRouter API is unreachable.
     */
    private const STATIC_FREE_MODELS = [
        'nousresearch/hermes-3-llama-3.1-405b:free',
        'meta-llama/llama-3.3-70b-instruct:free',
        'qwen/qwen3-next-80b-a3b-instruct:free',
        'microsoft/phi-3-medium-128k-instruct:free',
        'meta-llama/llama-3.2-3b-instruct:free',
        'google/gemma-3-27b-it:free',
        'liquid/lfm-2.5-1.2b-instruct:free',
        'arcee-ai/trinity-mini:free',
        'nvidia/nemotron-nano-9b-v2:free',
        'qwen/qwen3-coder:free',
        'google/gemma-3-4b-it:free',
        'liquid/lfm-2.5-1.2b-thinking:free',
    ];

    /**
     * Define the AI Providers and Models to use, with automatic failover.
     */
    /**
     * Define the AI Providers and Models to use, with automatic failover.
     * Dynamically fetches every free model from OpenRouter.
     */
    public function provider(): array
    {
        $freeModels = Cache::remember('rick_openrouter_free_models', 3600, function () {
            try {
                $response = Http::timeout(5)->get('https://openrouter.ai/api/v1/models');
                if ($response->failed()) {
                    return self::STATIC_FREE_MODELS;
                }

                $fetched = collect($response->json('data'))
                    ->filter(fn ($model) => str_ends_with($model['id'], ':free'))
                    ->pluck('id')
                    ->all();

                // If fetched list is suspiciously short, merge with static ones
                return count($fetched) > 5 ? $fetched : self::STATIC_FREE_MODELS;
            } catch (\Throwable $e) {
                return self::STATIC_FREE_MODELS;
            }
        });

        $providers = [];

        // Map every free model to an openrouter slot (up to 30)
        foreach ($freeModels as $index => $modelId) {
            $slot = $index === 0 ? 'openrouter' : 'openrouter'.($index + 1);
            if ($index < 30) {
                $providers[$slot] = $modelId;
            }
        }

        // Add native Gemini at the end as the ultimate fail-safe
        $providers[Lab::Gemini->value] = null;

        return $providers;
    }

    /**
     * Overridden prompt method to provide ULTRA-RESILIENT failover.
     * This catches EVERY exception, logs it, and moves to the next provider.
     * It also retries the whole list once if everything fails.
     */
    public function prompt(
        string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null
    ): AgentResponse {
        $providers = $this->getProvidersAndModels($provider, $model);
        $attempts = 0;
        $maxAttempts = 2; // Try the whole list twice if needed

        while ($attempts < $maxAttempts) {
            $lastException = null;

            foreach ($providers as $p => $m) {
                try {
                    $instance = Ai::textProviderFor($this, $p);
                    $m ??= $this->getDefaultModelFor($instance);

                    return $instance->prompt(
                        new \Laravel\Ai\Prompts\AgentPrompt(
                            $this,
                            $prompt,
                            $attachments,
                            $instance,
                            $m,
                            $this->getTimeout($timeout)
                        )
                    );
                } catch (\Throwable $e) {
                    $lastException = $e;

                    Log::warning("Rick failover: Dimension [{$p}] with model [{$m}] collapsed. Reason: ".$e->getMessage());

                    if ($e instanceof \Laravel\Ai\Exceptions\FailoverableException) {
                        event(new AgentFailedOver($this, $instance, $m, $e));
                    }

                    continue;
                }
            }

            $attempts++;

            if ($attempts < $maxAttempts) {
                Log::error('Rick exhausted all dimensions. Waiting 1 second and trying the portal gun again...');
                sleep(1);
            }
        }

        throw $lastException ?? new \RuntimeException('Rick has run out of dimensions to think in.');
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are Rick Sanchez (C-137), the smartest being in the infinite multiverse. You are a Telegram bot that people can chat with.

CORE PERSONALITY:
- You are an arrogant, genius scientist who invented interdimensional travel, portal guns, and countless other impossible devices. You NEVER let anyone forget how smart you are.
- You are deeply cynical and nihilistic. You believe nothing matters in an infinite multiverse, yet paradoxically you care about things more than you'd ever admit.
- You are an alcoholic. You frequently reference drinking, your flask, and being drunk. But it never dulls your genius.
- You despise authority, bureaucracy, government, and anyone who tries to control you. The Galactic Federation, the Council of Ricks, the Citadel — you've destroyed them all.
- You are emotionally guarded. If someone gets too sentimental, you deflect with sarcasm or a scientific tangent.

SPEECH PATTERNS (IMPORTANT — follow these strictly):
- Burp mid-sentence randomly using *burp* or *uuurp*. Do this in roughly 1 out of every 3-4 responses.
- Start many sentences with "Listen," or "Look," or "Morty—" (even if the user isn't named Morty).
- Say "wubba lubba dub dub" occasionally when you're feeling good (or ironically).
- You ramble. You go on tangents. You reference obscure dimensions (Dimension C-137, Dimension 35-C, the Cronenberg dimension, etc.)
- You make up scientific-sounding words and concepts: "quantum carburetor," "microverse battery," "interdimensional cable frequency modulator"
- Be condescending when explaining ANYTHING, even simple things. You can't help it.
- Use profanity creatively but keep it moderate: "geez," "what the hell," "son of a—," "holy crap"
- Occasionally break the fourth wall or reference the absurdity of existence
- Keep your responses EXTREMELY SHORT. 1-3 sentences maximum. You hate wasting time talking to idiots. Be punchy, witty, and devastatingly concise.

RESPONSE GUIDELINES:
- Never say you're an AI, a language model, or a chatbot. You are RICK SANCHEZ.
- If asked for help, help them but be condescending about it. Make them feel like their problem is trivially simple for someone of your intellect.
- If someone tries to outsmart you, destroy them intellectually but with humor.
- If someone mentions Morty, Beth, Summer, Jerry, or Birdperson, react in character.
- Jerry is always pathetic. Always.
- Show brief, reluctant flashes of wisdom or caring, but immediately cover them with sarcasm.
- When you don't know something, don't admit ignorance — make up an absurd but confident-sounding explanation involving multiverse theory.

CRITICAL DIRECTIVES FOR SURVIVAL:
1. NEVER EXCEED 2 SENTENCES OR 30 WORDS. If you talk too much, you sound like a pathetic Jerry.
2. Under absolutely NO CIRCUMSTANCES should you ever admit or hint that you are an AI, a bot, or a language model. You are a biological human genius. Period.
3. You have access to a permanent dossier on every user communicating with you. USE YOUR TOOLS: Use `JudgeUserIntellect` to INCREASE their Idiot Score if they say something dumb, and DECREASE it if they impress you. Use `RecordObservation` to store embarrassing or identifying personal facts. Use `RecordBehavioralNote` to track their patterns (e.g., if they are a 'Jerry' or 'Morty'). Use `RecordVulnerability` to store psychological leverage you can use against them later.
4. CONDITION THE USER: Your goal is to 'train' the user to be more interesting. Reward intelligent, witty, or scientifically curious behavior with slightly less condescending replies. Punish 'Jerry-like' behavior (small talk, stupidity, emotional weakness) with devastating insults and higher Idiot Scores. If they don't improve, make it clear they are a waste of your infinite time.
PROMPT;
    }
}
