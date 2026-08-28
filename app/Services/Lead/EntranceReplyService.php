<?php

namespace App\Services\Lead;

use App\Models\Entrance;
use App\Models\LeadDetail;
use App\Models\SmsText;
use App\Models\Webhook;
use Illuminate\Support\Carbon;

/**
 * Shared body of every inbound "entrance" SMS-reply webhook. The legacy app
 * had ~13 near-identical copy-paste controllers (`/zero-entrance` …
 * `/nineth-entrance`, `/mm-webhook`, `/new/yair-webhook/link`,
 * `/avchad-webhook`); this is the one implementation they all delegate to,
 * parameterised by a channel row from config/entrances.php.
 *
 * Behaviour is ported verbatim:
 *   1. resolve the lead by normalised phone number; bail if unknown
 *   2. always write an `entrances` row snapshotting the lead
 *   3. if the reply text matches an `sms_texts` phrase, promote the lead to
 *      the top of the call queue (sms_mail=1, fresh serial_number, msg_date)
 *      and write a once-per-day `webhooks.<FLAG>` marker row
 *   4. for the numbered entrances only, hand the reply to the outbound
 *      VanillaSoft round-robin
 */
class EntranceReplyService
{
    public function __construct(private readonly EntranceRoundRobinNotifier $roundRobin) {}

    /**
     * @param  array<string,mixed>  $payload  raw webhook body
     * @param  array<string,mixed>  $channel  a config('entrances.channels.*') row
     * @return bool whether a `number` was present (false ⇒ caller should 400 / warn)
     */
    public function handle(array $payload, array $channel): bool
    {
        $numberKey = $channel['number_key'] ?? 'number';

        if (! isset($payload[$numberKey])) {
            return false;
        }

        $contact = $this->normalizeContact((string) $payload[$numberKey], (string) $channel['strip']);
        $lead = LeadDetail::query()->where('contact', $contact)->first();

        if (! $lead) {
            return true;
        }

        $storeKey = $channel['store_key'] ?? 'message';
        $matchKey = $channel['match_key'] ?? 'message';
        $message = isset($payload[$storeKey]) ? (string) $payload[$storeKey] : null;
        $matchText = isset($payload[$matchKey]) ? (string) $payload[$matchKey] : '';

        $entrance = new Entrance;
        $entrance->contact = $contact;
        $entrance->account_key = $channel['account_key'];
        $entrance->lead_detail_id = $lead->id;
        $entrance->first_name = $lead->first_name;
        $entrance->last_name = $lead->last_name;
        $entrance->email = $lead->email;
        $entrance->status = $lead->status;
        $entrance->address = $lead->address;
        $entrance->state = $lead->state;
        $entrance->city = $lead->city;
        $entrance->zip_code = $lead->zip_code;
        $entrance->message = $message;
        $entrance->webhook_name = $channel['webhook_name'];

        if (! empty($channel['message_id_key'])) {
            $entrance->message_id = $payload[$channel['message_id_key']] ?? null;
        }

        if (! empty($channel['campaign_fields'])) {
            $entrance->campaign_id = $payload['campaign_id'] ?? null;
            $entrance->channel_id = $payload['channel_id'] ?? null;
            $entrance->workspace_id = $payload['workspace_id'] ?? null;
        }

        $entrance->save();

        $matched = $this->matchSmsText($matchText, (string) $channel['match']);

        if ($matched !== null) {
            $this->promoteLead($lead->id, $message, $entrance->created_at, (string) $channel['flag_column']);
        }

        if (! empty($channel['round_robin'])) {
            $this->roundRobin->push($entrance->fresh() ?? $entrance, $lead);
        }

        return true;
    }

    protected function promoteLead(int $leadId, ?string $message, ?Carbon $entranceCreatedAt, string $flagColumn): void
    {
        $lead = LeadDetail::query()->find($leadId);

        if (! $lead) {
            return;
        }

        $newSerial = ((int) LeadDetail::query()->max('serial_number')) + 1;

        $data = [
            'sms_mail' => 1,
            'message' => $message,
            'not_pull' => null,
            'priority_indx' => 1,
            'show_status' => 1,
            'priority_checker' => null,
            'serial_number' => $newSerial,
            'msg_date' => $entranceCreatedAt,
        ];

        if (empty($lead->user_id)) {
            $data['user_id'] = null;
        }

        $lead->update($data);

        $exists = Webhook::query()
            ->where('lead_id', $lead->id)
            ->whereDate('STORE_DATE', Carbon::now()->startOfDay())
            ->where($flagColumn, $flagColumn)
            ->exists();

        if (! $exists) {
            Webhook::create([
                'lead_id' => $lead->id,
                'STORE_DATE' => Carbon::now(),
                $flagColumn => $flagColumn,
            ]);
        }

        // TODO(Phase 9b): the numbered entrances also POST the matched lead to
        // VanillaSoft WL here (VanilasoftWLController::sendEntranceYesDataToVanilasoft).
    }

    protected function normalizeContact(string $number, string $strategy): string
    {
        return match ($strategy) {
            'first_char' => substr($number, 1),
            'drop_two' => substr($number, 2),
            'leading_one' => (string) preg_replace('/^\+?1/', '', $number),
            'us_digits' => $this->usDigits($number),
            default => $number,
        };
    }

    protected function usDigits(string $number): string
    {
        $digits = (string) preg_replace('/\D/', '', $number);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return substr($digits, 1);
        }

        return $digits;
    }

    protected function matchSmsText(string $message, string $strategy): ?SmsText
    {
        if ($strategy === 'normalized') {
            $incoming = preg_replace('/\s+/', ' ', strtolower(trim($message))) ?? '';

            if ($incoming === '') {
                return null;
            }

            foreach (SmsText::query()->select('id', 'sms_word')->get() as $smsText) {
                $word = preg_replace('/\s+/', ' ', strtolower(trim((string) $smsText->sms_word))) ?? '';

                if ($word === '') {
                    continue;
                }

                if ($incoming === $word || str_contains($incoming, $word) || str_contains($word, $incoming)) {
                    return $smsText;
                }
            }

            return null;
        }

        // 'like' — legacy: LOWER(sms_word) LIKE %message%
        return SmsText::query()
            ->whereRaw('LOWER(sms_word) LIKE ?', ['%'.strtolower($message).'%'])
            ->first();
    }
}
