<?php

namespace App\Services\Ai;

/**
 * The default. Never calls out. `available()` is false so callers fall back to
 * their fixed content; the generate methods throw if invoked anyway.
 */
class NullLlmGateway implements LlmGateway
{
    public function available(): bool
    {
        return false;
    }

    public function generateText(string $system, string $prompt, array $options = []): string
    {
        throw new \RuntimeException('The LLM gateway is disabled (config/ai.php).');
    }

    public function generateJson(string $system, string $prompt, array $options = []): array
    {
        throw new \RuntimeException('The LLM gateway is disabled (config/ai.php).');
    }
}
