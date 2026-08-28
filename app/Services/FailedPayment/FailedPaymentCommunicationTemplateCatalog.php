<?php

namespace App\Services\FailedPayment;

use App\Models\EmailSignature;
use App\Models\FailedPaymentCase;
use App\Models\SmsSignature;
use App\Models\VoiceSignature;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class FailedPaymentCommunicationTemplateCatalog
{
    public function channels(): array
    {
        return ['email', 'sms', 'voice', 'voicemail'];
    }

    public function templates(): array
    {
        return $this->loadTemplatesForUser(Auth::guard('web')->id());
    }

    public function templateOptions(?int $userId = null): array
    {
        return collect($this->loadTemplatesForUser($userId))->map(function (array $template) {
            return [
                'id' => $template['id'],
                'label' => $template['label'],
                'version' => $template['version'],
                'channels' => $template['channels'],
            ];
        })->values()->all();
    }

    public function templateOptionsForChannel(string $channel, ?int $userId = null): array
    {
        $channel = strtolower(trim($channel));

        return collect($this->loadTemplatesForUser($userId))
            ->filter(fn (array $template) => in_array($channel, $template['channels'], true))
            ->map(function (array $template) {
                return [
                    'id' => $template['id'],
                    'label' => $template['label'],
                    'version' => $template['version'],
                    'channels' => $template['channels'],
                    'type' => $template['type'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    public function templateOptionsByChannel(?int $userId = null): array
    {
        return collect($this->channels())
            ->mapWithKeys(fn (string $channel) => [$channel => $this->templateOptionsForChannel($channel, $userId)])
            ->all();
    }

    public function allowedTemplateIdsForChannel(string $channel): array
    {
        $channel = strtolower(trim($channel));

        return collect($this->loadTemplatesForUser(null))
            ->filter(fn (array $template) => in_array($channel, $template['channels'], true))
            ->keys()
            ->values()
            ->all();
    }

    public function defaultTemplateIdForChannel(string $channel): ?string
    {
        $channel = strtolower(trim($channel));

        return collect($this->allowedTemplateIdsForChannel($channel))->first();
    }

    public function templateVersion(string $templateId): int
    {
        return (int) Arr::get($this->loadTemplatesForUser(null), $templateId.'.version', 1);
    }

    public function render(FailedPaymentCase $case, string $channel, string $templateId, array $context = []): array
    {
        $channel = strtolower(trim($channel));
        $templateId = strtolower(trim($templateId));
        $template = $this->loadTemplatesForUser(null)[$templateId] ?? null;

        if (! $template || ! in_array($channel, $template['channels'], true)) {
            throw new \RuntimeException('Unsupported failed-payment communication template.');
        }

        $recipient = (string) ($context['recipient'] ?? '');
        $maskedRecipient = (string) ($context['masked_recipient'] ?? $this->maskRecipient($recipient));
        $name = $this->safeText(trim((string) (($case->client?->first_name ?? '').' '.($case->client?->last_name ?? ''))));
        $policy = $this->safeText((string) ($case->client?->policy_number ?? ''));
        $subscription = $this->safeText((string) ($case->arb_subscription_id ?? ''));
        $transaction = $this->safeText((string) ($case->anet_transaction_id ?? ''));
        $amount = '$'.number_format((float) ($case->failed_amount ?? 0), 2);
        $retryStage = $this->safeText((string) Arr::get($context, 'retry_stage', 'manual'));
        $retryStageLabel = $this->safeText((string) Arr::get($context, 'retry_stage_label', ''));
        $retryDate = $this->safeText((string) Arr::get($context, 'retry_date', optional($case->next_action_at)->format('Y-m-d H:i:s') ?? 'soon'));
        $stageIntro = $this->buildStageIntro($retryStage, $retryStageLabel);
        $subject = null;
        $body = '';
        $audioUrl = null;
        $templateType = (string) ($template['type'] ?? 'signature');

        if ($templateType === 'email') {
            $subject = $this->replacePlaceholders((string) ($template['subject'] ?? 'Failed payment update'), [
                'name' => $name,
                'policy' => $policy,
                'subscription' => $subscription,
                'transaction' => $transaction,
                'transaction_tail' => $this->tail($transaction),
                'amount' => $amount,
                'retry_stage' => $retryStage,
                'retry_stage_label' => $retryStageLabel,
                'retry_date' => $retryDate,
                'stage_intro' => $stageIntro,
                'masked_recipient' => $maskedRecipient,
            ]);
            $body = $this->replacePlaceholders((string) ($template['body'] ?? ''), [
                'name' => $name,
                'policy' => $policy,
                'subscription' => $subscription,
                'transaction' => $transaction,
                'transaction_tail' => $this->tail($transaction),
                'amount' => $amount,
                'retry_stage' => $retryStage,
                'retry_stage_label' => $retryStageLabel,
                'retry_date' => $retryDate,
                'stage_intro' => $stageIntro,
                'masked_recipient' => $maskedRecipient,
            ]);
        } elseif ($templateType === 'sms') {
            $body = $this->replacePlaceholders((string) ($template['body'] ?? ''), [
                'name' => $name,
                'policy' => $policy,
                'subscription' => $subscription,
                'transaction' => $transaction,
                'transaction_tail' => $this->tail($transaction),
                'amount' => $amount,
                'retry_stage' => $retryStage,
                'retry_stage_label' => $retryStageLabel,
                'retry_date' => $retryDate,
                'stage_intro' => $stageIntro,
                'masked_recipient' => $maskedRecipient,
            ]);

            // Personalize every SMS with a "Dear ((<first name>)), " greeting so admins
            // don't need to type {name} into each signature themselves.
            $firstName = $this->safeText((string) ($case->client?->first_name ?? ''));
            $greetingName = $firstName !== '' ? $firstName : $name;
            if ($greetingName !== '' && $body !== '') {
                $body = 'Dear (('.$greetingName.')), '.$body;
            }
        } elseif ($templateType === 'voice') {
            $audioUrl = (string) ($template['audio_url'] ?? '');
            $body = trim('Voice signature selected: '.(string) ($template['label'] ?? 'Voice signature'));
        }

        $plain = trim(implode("\n", array_filter([
            $body,
        ])));

        return [
            'template_id' => $template['id'],
            'template_label' => $template['label'],
            'template_version' => $template['version'],
            'channel' => $channel,
            'subject' => $subject,
            'message' => $this->safeText($plain),
            'body_html' => $templateType === 'email' ? (string) ($template['body'] ?? '') : null,
            'recipient' => $recipient !== '' ? $recipient : null,
            'masked_recipient' => $maskedRecipient !== '' ? $maskedRecipient : null,
            'audio_url' => $audioUrl,
            'metadata' => array_filter([
                'template_id' => $template['id'],
                'template_label' => $template['label'],
                'template_version' => $template['version'],
                'channel' => $channel,
                'retry_stage' => $retryStage,
                'preview_policy' => $policy,
                'template_type' => $templateType,
                'audio_url' => $audioUrl,
            ], fn ($value) => ! is_null($value) && $value !== ''),
        ];
    }

    public function maskRecipient(?string $recipient): string
    {
        $recipient = $this->safeText((string) $recipient);

        if ($recipient === '') {
            return '';
        }

        if (str_contains($recipient, '@')) {
            [$local, $domain] = array_pad(explode('@', $recipient, 2), 2, '');

            if ($domain === '') {
                return $this->maskFallback($recipient);
            }

            $first = mb_substr($local, 0, 1);

            return $first.'***@'.$domain;
        }

        return $this->maskPhone($recipient);
    }

    protected function safeText(string $value): string
    {
        // Signature bodies are often authored in a rich-text editor. Turn line-break
        // and block-boundary tags into a space first so stripping tags doesn't glue
        // adjacent words together (e.g. "...2206" + "American..." -> "2206American").
        $value = preg_replace('/<\s*(br|\/p|\/div|\/li|\/h[1-6])\s*\/?>/i', ' ', $value) ?? $value;
        $value = strip_tags($value);
        // Decode leftover HTML entities (e.g. "&amp;" -> "&") from that rich-text content.
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function loadTemplatesForUser(?int $userId): array
    {
        $templates = [];

        foreach ($this->emailTemplates($userId) as $template) {
            $templates[$template['id']] = $template;
        }

        foreach ($this->systemEmailTemplates() as $template) {
            $templates[$template['id']] = $template;
        }

        foreach ($this->smsTemplates($userId) as $template) {
            $templates[$template['id']] = $template;
        }

        foreach ($this->voiceTemplates($userId) as $template) {
            $templates[$template['id']] = $template;
        }

        return $templates;
    }

    protected function emailTemplates(?int $userId): array
    {
        $query = EmailSignature::query();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (EmailSignature $signature): array {
                $id = 'email_signature:'.$signature->id;

                return [
                    $id => [
                        'id' => $id,
                        'label' => trim((string) $signature->name) !== '' ? (string) $signature->name : 'Email Signature #'.$signature->id,
                        'version' => 1,
                        'channels' => ['email'],
                        'type' => 'email',
                        'subject' => (string) $signature->subject,
                        'body' => (string) $signature->eamil_text,
                    ],
                ];
            })
            ->all();
    }

    protected function systemEmailTemplates(): array
    {
        return [
            'payment_faild' => [
                'id' => 'payment_faild',
                'label' => 'Payment Failed Mail',
                'version' => 1,
                'channels' => ['email'],
                'type' => 'email',
                'subject' => 'Payment Failed - Update Needed',
                'body' => 'Payment failed notification for {name}.',
                'view' => 'emails.payment_faild',
            ],
        ];
    }

    protected function smsTemplates(?int $userId): array
    {
        $query = SmsSignature::query();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (SmsSignature $signature): array {
                $id = 'sms_signature:'.$signature->id;

                return [
                    $id => [
                        'id' => $id,
                        'label' => trim((string) $signature->name) !== '' ? (string) $signature->name : 'SMS Signature #'.$signature->id,
                        'version' => 1,
                        'channels' => ['sms'],
                        'type' => 'sms',
                        'body' => (string) $signature->message_text,
                    ],
                ];
            })
            ->all();
    }

    protected function voiceTemplates(?int $userId): array
    {
        $query = VoiceSignature::query();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (VoiceSignature $signature): array {
                $id = 'voice_signature:'.$signature->id;
                $audioUrl = asset('audio_signatures/'.$signature->audio_file);

                return [
                    $id => [
                        'id' => $id,
                        'label' => trim((string) $signature->name) !== '' ? (string) $signature->name : 'Voice Signature #'.$signature->id,
                        'version' => 1,
                        'channels' => ['voice', 'voicemail'],
                        'type' => 'voice',
                        'audio_url' => $audioUrl,
                    ],
                ];
            })
            ->all();
    }

    protected function buildStageIntro(string $retryStage, string $retryStageLabel): string
    {
        return match ($retryStage) {
            'manual' => '',
            'initial' => "This is the initial follow-up regarding your account.\n",
            'retry_1' => "This is the first scheduled retry notice for your account.\n",
            'retry_3' => "This is the third scheduled retry notice for your account.\n",
            'retry_7' => "This is the seventh-day retry notice for your account.\n",
            'retry_14' => "This is the fourteenth-day retry notice for your account.\n",
            'retry_30' => "This is the final scheduled retry notice for your account.\n",
            'final_warning' => "This is your final reminder before the next action in the retry sequence.\n",
            'recovered' => "This message confirms your account has been recovered and no further action is required.\n",
            default => $retryStageLabel !== ''
                ? $retryStageLabel."\n"
                : "This message corresponds to the {$retryStage} retry stage.\n",
        };
    }

    protected function replacePlaceholders(string $template, array $values): string
    {
        $replace = [];

        foreach ($values as $key => $value) {
            $replace['{'.$key.'}'] = (string) $value;
        }

        return strtr($template, $replace);
    }

    protected function maskReference(string $value): string
    {
        $value = $this->safeText($value);

        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 4) {
            return str_repeat('*', max(4, strlen($value)));
        }

        return str_repeat('*', max(4, strlen($value) - 4)).substr($value, -4);
    }

    protected function maskPhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return $this->maskFallback($value);
        }

        if (strlen($digits) <= 4) {
            return str_repeat('*', max(4, strlen($digits)));
        }

        return str_repeat('*', max(4, strlen($digits) - 4)).substr($digits, -4);
    }

    protected function maskFallback(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 4) {
            return str_repeat('*', max(4, strlen($value)));
        }

        return substr($value, 0, 1).str_repeat('*', max(3, strlen($value) - 4)).substr($value, -3);
    }

    protected function tail(string $value): string
    {
        $value = $this->safeText($value);

        return $value === '' ? '' : substr($value, -4);
    }
}
