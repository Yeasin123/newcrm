<?php

namespace App\Services\Lead;

use App\Models\ConstantContact;
use App\Models\LeadDetail;
use App\Models\Webhook;
use Carbon\Carbon;

/**
 * Shared body of the email-engagement lead webhooks — `/quote/email/webhook`,
 * `/pquote/email/webhook`, `/cel/webhook`. One implementation driven by a
 * config('email_webhooks.channels.*') row; ported verbatim from the legacy
 * QuoteController / CEWebhookController.
 *
 * Flow: resolve the lead by email OR phone. If found, or after creating a new
 * one (campaign 99, sms_mail 2, the channel's lead_tag), write a
 * constant_contacts row, push the lead to the top of the queue (sms_mail=2,
 * priority_indx=1, show_status=1, msg_date), and write a once-per-day
 * webhooks.<FLAG> marker.
 */
class EmailWebhookLeadService
{
    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $channel  a config('email_webhooks.channels.*') row
     */
    public function handle(array $payload, array $channel): void
    {
        $email = $payload[$channel['email_key']] ?? null;
        $phone = $this->strip((string) ($payload[$channel['phone_key']] ?? ''), $channel['phone_strip'] ?? null);

        $lead = LeadDetail::query()
            ->where('email', $email)
            ->orWhere('contact', $phone)
            ->first();

        $isNew = false;

        if (! $lead) {
            $isNew = true;
            $lead = LeadDetail::query()->create(array_merge(
                $this->mapFields($channel['new_fields'] ?? [], $payload, $email, $phone),
                [
                    'contact' => $phone,
                    'email' => $email,
                    'campaign_id' => $channel['campaign_id'],
                    'sms_mail' => 2,
                    'lead_tag' => $channel['lead_tag'],
                ],
            ));
        }

        $cc = ConstantContact::query()->create([
            'lead_id' => $lead->id,
            'email_text' => $channel['cc_text'],
            'campaign' => null,
            'email_time' => $this->emailTime($payload, (string) $channel['time']),
            'webhook_name' => $channel['cc_name'],
        ]);

        $lead = LeadDetail::query()->find($cc->lead_id);

        if (! $lead) {
            return;
        }

        $update = [
            'sms_mail' => 2,
            'message' => $channel['cc_text'],
            'priority_indx' => 1,
            'show_status' => 1,
            'priority_checker' => null,
            'not_pull' => null,
            'msg_date' => $cc->created_at,
        ];

        if (! empty($channel['always_null_user']) || empty($lead->user_id)) {
            $update['user_id'] = null;
        }

        // On a pre-existing lead the source fields (name / email / contact …)
        // are refreshed from the payload; on a just-created lead they were set
        // at create time and the legacy re-update omits them.
        if (! $isNew) {
            $update = array_merge($update, $this->mapFields($channel['fields'] ?? [], $payload, $email, $phone));
        }

        $lead->update($update);

        $this->writeDayFlag($lead->id, (string) $channel['flag_column']);
    }

    /**
     * @param  array<string,string>  $map  lead column => payload key ('@email' / '@phone' resolve
     *                                     to the already-resolved values)
     * @return array<string,mixed>
     */
    protected function mapFields(array $map, array $payload, $email, string $phone): array
    {
        $out = [];

        foreach ($map as $column => $source) {
            $out[$column] = match ($source) {
                '@email' => $email,
                '@phone' => $phone,
                default => $payload[$source] ?? null,
            };
        }

        return $out;
    }

    protected function strip(string $phone, ?string $strategy): string
    {
        if ($strategy === 'cel') {
            // legacy: preg_replace('/^1?/', '', preg_replace('/[^\d]/', '', $phone))
            return (string) preg_replace('/^1?/', '', (string) preg_replace('/[^\d]/', '', $phone));
        }

        return $phone;
    }

    protected function emailTime(array $payload, string $mode): string
    {
        if ($mode === 'date_time') {
            $dateTime = new \DateTime(trim(($payload['Date'] ?? '').' '.($payload['Time'] ?? '')));

            return $dateTime->format('m/d/Y H:i');
        }

        return Carbon::now()->format('m/d/Y H:i');
    }

    protected function writeDayFlag(int $leadId, string $flagColumn): void
    {
        $exists = Webhook::query()
            ->where('lead_id', $leadId)
            ->whereDate('STORE_DATE', Carbon::now()->startOfDay())
            ->where($flagColumn, $flagColumn)
            ->exists();

        if (! $exists) {
            Webhook::create([
                'lead_id' => $leadId,
                'STORE_DATE' => Carbon::now(),
                $flagColumn => $flagColumn,
            ]);
        }
    }
}
