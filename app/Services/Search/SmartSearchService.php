<?php

namespace App\Services\Search;

use App\Services\Ai\LlmGateway;
use Illuminate\Support\Arr;

/**
 * Turns a plain-language search phrase ("Jeff's failed customers", "kitchen
 * plan customers in Nevada") into a small, whitelisted set of structured
 * filters the Search screen already knows how to run.
 *
 * "AI as helper, not boss": the LLM only proposes values for known fields —
 * it never writes SQL. When the LLM gateway is unavailable (the default,
 * config('ai.enabled') off) the phrase is used as a plain keyword instead,
 * so the box still works.
 */
class SmartSearchService
{
    /** Fields the interpreter is allowed to populate. */
    private const FIELDS = ['name', 'email', 'phone', 'city', 'state', 'policy_number', 'salesman', 'status', 'plan', 'entity'];

    private const STATUSES = ['active', 'failed', 'bypass', 'cancelled', 'renewal', 'inactive'];

    private const PLANS = ['monthly', 'yearly'];

    private const ENTITIES = ['customers', 'leads'];

    public function __construct(private readonly LlmGateway $llm) {}

    /**
     * @return array{filters: array<string,string>, entity: ?string, note: string, ai: bool}
     */
    public function interpret(string $phrase): array
    {
        $phrase = trim($phrase);

        if ($phrase === '') {
            return ['filters' => [], 'entity' => null, 'note' => '', 'ai' => false];
        }

        if (! $this->llm->available()) {
            return [
                'filters' => ['name' => $phrase],
                'entity' => null,
                'note' => __('AI search is off — ran a keyword search on ":q".', ['q' => $phrase]),
                'ai' => false,
            ];
        }

        try {
            $raw = $this->llm->generateJson($this->system(), $phrase, ['max_tokens' => 300]);
        } catch (\Throwable $e) {
            return [
                'filters' => ['name' => $phrase],
                'entity' => null,
                'note' => __('AI could not parse that — ran a keyword search instead.'),
                'ai' => false,
            ];
        }

        $clean = $this->sanitise($raw);
        $entity = $clean['entity'] ?? null;
        unset($clean['entity']);

        return [
            'filters' => $clean,
            'entity' => $entity,
            'note' => $clean === []
                ? __('AI did not find a filter in that phrase.')
                : __('AI read this as: :summary', ['summary' => $this->summarise($clean, $entity)]),
            'ai' => true,
        ];
    }

    private function system(): string
    {
        return implode("\n", [
            'You convert a CRM search phrase into a compact JSON object of filters. Output ONLY the JSON object.',
            'Allowed keys: name, email, phone, city, state, policy_number, salesman, status, plan, entity.',
            'status is one of: '.implode(', ', self::STATUSES).'. plan is one of: '.implode(', ', self::PLANS).'.',
            'entity is "customers" or "leads" — set it only if the phrase clearly names one.',
            'Use two-letter codes for US state. Omit any key you are not confident about. Never invent names.',
            'Examples:',
            '"Jeff\'s failed customers" => {"salesman":"Jeff","status":"failed","entity":"customers"}',
            '"kitchen plan customers in Nevada" => {"name":"kitchen","state":"NV","entity":"customers"}',
            '"Max\'s monthly accounts" => {"salesman":"Max","plan":"monthly"}',
        ]);
    }

    /**
     * @param  array<string,mixed>  $raw
     * @return array<string,string>
     */
    private function sanitise(array $raw): array
    {
        $out = [];

        foreach (Arr::only($raw, self::FIELDS) as $key => $value) {
            $value = is_scalar($value) ? trim((string) $value) : '';

            if ($value === '') {
                continue;
            }

            $value = match ($key) {
                'status' => in_array(strtolower($value), self::STATUSES, true) ? strtolower($value) : null,
                'plan' => in_array(strtolower($value), self::PLANS, true) ? strtolower($value) : null,
                'entity' => in_array(strtolower($value), self::ENTITIES, true) ? strtolower($value) : null,
                'state' => strtoupper(substr($value, 0, 2)),
                default => mb_substr($value, 0, 120),
            };

            if ($value !== null && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** @param array<string,string> $filters */
    private function summarise(array $filters, ?string $entity): string
    {
        $parts = [];
        foreach ($filters as $k => $v) {
            $parts[] = str_replace('_', ' ', $k).' = '.$v;
        }
        if ($entity) {
            $parts[] = 'in '.$entity;
        }

        return implode(', ', $parts);
    }
}
