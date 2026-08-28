<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

/**
 * Claude (Anthropic Messages API) over the Laravel HTTP client — the same
 * JSON-over-HTTP approach this codebase uses for every other external API
 * (Authorize.Net, Telnyx, …) rather than pulling in an SDK. Bound only when
 * config('ai.enabled') is true and an api key is set.
 */
class ClaudeLlmGateway implements LlmGateway
{
    public function available(): bool
    {
        return (bool) config('ai.enabled') && config('ai.claude.api_key') !== '';
    }

    public function generateText(string $system, string $prompt, array $options = []): string
    {
        $message = $this->call($system, $prompt, $options);

        $text = collect($message['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return trim($text);
    }

    public function generateJson(string $system, string $prompt, array $options = []): array
    {
        $raw = $this->generateText(
            $system."\n\nReply with ONLY a single minified JSON object and nothing else — no markdown, no prose.",
            $prompt,
            $options,
        );

        // Tolerate a stray ```json fence or leading text.
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('LLM did not return a JSON object: '.mb_substr($raw, 0, 200));
        }

        return $decoded;
    }

    /** @return array<string,mixed> the decoded Messages API response */
    protected function call(string $system, string $prompt, array $options): array
    {
        $cfg = config('ai.claude');

        $response = Http::baseUrl((string) $cfg['base_url'])
            ->timeout((int) $cfg['timeout'])
            ->withHeaders([
                'x-api-key' => (string) $cfg['api_key'],
                'anthropic-version' => (string) $cfg['version'],
            ])
            ->acceptJson()
            ->asJson()
            ->post('/v1/messages', [
                'model' => $options['model'] ?? $cfg['model'],
                'max_tokens' => (int) ($options['max_tokens'] ?? $cfg['max_tokens']),
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Claude API error '.$response->status().': '.$response->body());
        }

        $body = $response->json();

        if (($body['stop_reason'] ?? null) === 'refusal') {
            throw new \RuntimeException('Claude declined the request.');
        }

        return is_array($body) ? $body : [];
    }
}
