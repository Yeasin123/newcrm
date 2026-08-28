<?php

namespace App\Services\Dialer;

use App\Models\LeadDetail;
use App\Models\LeadStatus;
use App\Models\Note;
use App\Models\TrackButton;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Records a Vanilasoft viewboard call disposition. Ported from the legacy
 * VanilasoftController::vanilasoftViewboardWebhook + trackButton + findStatus.
 *
 * Maps the dialer disposition to a leads_status row, rolls it into the lead's
 * latest track_buttons record (or opens a new one), writes a note, reassigns
 * the lead to the rep, and — for Sale / Doobie — posts to Zapier (only when
 * config('dialer.zapier_enabled')).
 */
class ViewboardDispositionService
{
    /**
     * @param  array<string,mixed>  $payload  the raw request `contact` object
     * @return bool true when the disposition matched a status + lead + rep
     */
    public function record(array $payload): bool
    {
        $status = $this->normalizeStatus((string) ($payload['status'] ?? ''));

        $leadStatus = LeadStatus::query()->where('title', $status)->first();
        $lead = LeadDetail::query()->where('contact', $payload['PHONE'] ?? null)->first();
        $rep = User::query()->where('v_salesman_id', $payload['userid'] ?? null)->first();

        if (! $leadStatus || ! $lead || ! $rep) {
            return false;
        }

        $context = array_merge($payload, [
            'buttonValue' => $status,
            'comments' => $payload['notes'] ?? null,
            'timeSpent' => $payload['callduration'] ?? null,
            'leadId' => $lead->id,
            'user_id' => $rep->id,
            'leadStatus' => $leadStatus->id,
            'email' => $lead->email,
        ]);

        $this->trackButton($context);

        $lead->update(['status' => $leadStatus->id, 'user_id' => $rep->id]);

        if (in_array($status, ['Sale', 'Doobie'], true)) {
            $this->postSaleToZapier($lead->email, $lead->contact);
        }

        // Legacy repeats the status write across every lead on that number.
        LeadDetail::query()->where('contact', $payload['PHONE'])->update(['status' => $leadStatus->id]);

        return true;
    }

    protected function trackButton(array $data): void
    {
        $buttonValue = $data['buttonValue'];
        $comments = $data['comments'];
        $seconds = $this->timeToSeconds((string) ($data['timeSpent'] ?? '0:0:0'));

        $requestDateTime = Carbon::createFromFormat('m/d/Y h:iA', ($data['date'] ?? '').' '.($data['time'] ?? ''))
            ->format('m-d-Y h:i A');

        $latest = TrackButton::query()->where('lead_id', $data['leadId'])->latest()->first();
        $commentsArray = is_array($comments) ? $comments : [$comments];

        $openNewRow = fn () => TrackButton::create([
            'user_id' => $data['user_id'],
            'lead_id' => $data['leadId'],
            'status' => $data['leadStatus'],
            'button_text' => json_encode([$buttonValue => 1]),
            'create_date' => $requestDateTime,
            'duration' => $seconds,
            'comments' => json_encode($commentsArray),
        ]);

        if ($latest && empty($latest->button_text)) {
            $openNewRow();
        } elseif ($latest && empty($latest->status)) {
            $counts = json_decode((string) $latest->button_text, true) ?: [];
            $counts[$buttonValue] = ($counts[$buttonValue] ?? 0) + 1;

            $existing = $latest->comments ? (json_decode((string) $latest->comments, true) ?: []) : [];
            $merged = array_values(array_unique(array_merge(is_array($existing) ? $existing : [], $commentsArray)));

            $latest->update([
                'button_text' => json_encode($counts),
                'duration' => (int) $latest->duration + $seconds,
                'create_date' => $requestDateTime,
                'user_id' => $data['user_id'],
                'status' => $data['leadStatus'] ?? $latest->status,
                'comments' => json_encode($merged),
            ]);
        } else {
            $openNewRow();
        }

        if (! is_null($comments)) {
            Note::create([
                'lead_id' => $data['leadId'],
                'user_id' => $data['user_id'],
                'date_time' => $requestDateTime,
                'notes' => $comments,
                // Legacy: Client::find($email) — an email is not a PK, so this is always null.
                'client_id' => null,
            ]);
        }
    }

    protected function normalizeStatus(string $status): string
    {
        return match ($status) {
            'Sale', 'Has One', 'Doobie', 'Spoke With', 'A/Cust', 'Wrong Number',
            'Realtor', 'Renwal', 'Call Back' => $status,
            'SpokeWith' => (string) preg_replace('/([a-z])([A-Z])/', '$1 $2', $status),
            'No Answer 1' => 'No Answer',
            'Spoke With 1' => 'Spoke With',
            'Call Back 1' => 'Call Back',
            default => 'No Answer',
        };
    }

    protected function timeToSeconds(string $time): int
    {
        $parts = array_pad(explode(':', $time), 3, 0);

        return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
    }

    protected function postSaleToZapier(?string $email, ?string $contact): void
    {
        if (! config('dialer.zapier_enabled') || ! config('dialer.zapier_sale_url')) {
            return;
        }

        try {
            Http::acceptJson()->post((string) config('dialer.zapier_sale_url'), [
                'email' => $email,
                'contact' => $contact,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Dialer Zapier sale post failed.', ['error' => $e->getMessage()]);
        }
    }
}
