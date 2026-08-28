<?php

namespace App\Services\Ai;

/**
 * A minimal text-in / text-or-JSON-out LLM interface. Bound to NullLlmGateway
 * unless config('ai.enabled') and a key are set, so every caller must have a
 * deterministic fallback for when `available()` is false or a call throws.
 */
interface LlmGateway
{
    public function available(): bool;

    /**
     * One completion. Returns the assistant's text (already trimmed).
     *
     * @param  array<string,mixed>  $options  e.g. ['max_tokens' => 400, 'model' => '...']
     */
    public function generateText(string $system, string $prompt, array $options = []): string;

    /**
     * One completion whose reply must be a single JSON object. Returns the
     * decoded array. Throws on an empty / unparseable reply.
     *
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function generateJson(string $system, string $prompt, array $options = []): array;
}
