<?php

namespace App\Services\Lead;

use App\Models\LeadDetail;
use App\Services\Ai\LlmGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Drafts a lead welcome email. Ported from the legacy LeadWelcomeAgentService;
 * the OpenAI Responses call is replaced by App\Services\Ai\LlmGateway (Claude).
 * AI is a helper only — every failure path returns the same fixed fallback
 * copy, and the return shape always carries `is_ai_generated` + `reason`.
 *
 * @return array{subject:string, body:string, is_ai_generated:bool, reason:string}
 */
class LeadWelcomeAgentService
{
    public function __construct(private readonly LlmGateway $llm) {}

    public function compose(LeadDetail $lead): array
    {
        $fallback = $this->fallbackCopy($lead);

        if (! $this->llm->available()) {
            return array_merge($fallback, ['reason' => 'llm_unavailable']);
        }

        try {
            $decoded = $this->llm->generateJson($this->systemPrompt(), $this->buildPrompt($lead), [
                'max_tokens' => 700,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Lead welcome AI request failed, using fallback copy.', [
                'lead_id' => $lead->id, 'error' => $e->getMessage(),
            ]);

            return array_merge($fallback, ['reason' => 'llm_request_failed']);
        }

        $validator = Validator::make($decoded, [
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:40', 'max:3000'],
        ]);

        if ($validator->fails()) {
            Log::warning('Lead welcome AI response failed validation, using fallback copy.', [
                'lead_id' => $lead->id, 'errors' => $validator->errors()->all(),
            ]);

            return array_merge($fallback, ['reason' => 'validation_failed']);
        }

        return [
            'subject' => $this->sanitizeLine($decoded['subject']),
            'body' => $this->sanitizeBody($decoded['body']),
            'is_ai_generated' => true,
            'reason' => 'llm_generated',
        ];
    }

    public function fallbackCopy(LeadDetail $lead): array
    {
        $name = trim(implode(' ', array_filter([$lead->first_name, $lead->last_name])));
        $greeting = $name !== '' ? $name : 'there';

        return [
            'subject' => 'Thank you for contacting us',
            'body' => "Hi {$greeting},\n\nThanks for reaching out. We have received your lead and our team will review it as soon as possible.\n\nIf you need anything urgent, please reply to this email and we will help you next.",
            'is_ai_generated' => false,
            'reason' => 'fallback_copy',
        ];
    }

    protected function buildPrompt(LeadDetail $lead): string
    {
        $campaign = optional($lead->campaign)->name ?: 'Unknown campaign';
        $name = trim(implode(' ', array_filter([$lead->first_name, $lead->last_name])));

        return <<<PROMPT
        Create a short welcome email for a newly created lead.

        Return only JSON with:
        - subject: max 120 characters
        - body: plain text only, warm and professional, 2 short paragraphs max

        Lead details:
        - name: {$name}
        - email: {$lead->email}
        - phone: {$lead->contact}
        - campaign: {$campaign}

        Writing rules:
        - Do not mention any AI or vendor.
        - Do not mention internal system details.
        - Do not promise anything you cannot verify.
        - Keep it helpful, human, and concise.
        PROMPT;
    }

    protected function systemPrompt(): string
    {
        return 'You write safe, concise lead welcome email copy for a home-warranty CRM. Return only valid JSON with the keys "subject" and "body".';
    }

    protected function sanitizeLine(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim(strip_tags($value))) ?: '';
    }

    protected function sanitizeBody(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace("/\r\n?/", "\n", $value) ?? $value;
        $value = preg_replace("/[ \t]+\n/", "\n", $value) ?? $value;

        return trim($value);
    }
}
