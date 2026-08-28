<?php

namespace App\Services\Lead;

use App\Models\Entrance;
use App\Models\LeadDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Live VanillaSoft round-robin. Ported from the legacy WebhookService: rotate
 * 1→2→3→4→1 keyed on entrances.last_webhook_used, POST {NUMBER, SMS} to the
 * chosen URL, then persist the advanced cursor. Only bound when
 * config('entrances.outbound_enabled') is true.
 */
class HttpEntranceRoundRobinNotifier implements EntranceRoundRobinNotifier
{
    public function push(Entrance $entrance, LeadDetail $lead): void
    {
        $urls = (array) config('entrances.round_robin_urls', []);

        if ($urls === []) {
            return;
        }

        $cursor = Entrance::query()->where('lead_detail_id', $lead->id)->first();

        if (! $cursor) {
            return;
        }

        $last = (int) ($cursor->last_webhook_used ?? 0);
        $next = $last === 4 ? 1 : $last + 1;
        $url = $urls[$next] ?? null;

        if (! $url) {
            return;
        }

        try {
            Http::withHeaders(['Accept' => 'application/json'])->post($url, [
                'NUMBER' => $entrance->contact,
                'SMS' => $entrance->message.' '.Carbon::now()->format('m/d/y h:i A'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Entrance round-robin push failed.', ['error' => $e->getMessage(), 'lead_id' => $lead->id]);
        }

        // Legacy advances the cursor regardless of the HTTP outcome.
        $cursor->last_webhook_used = $next === 5 ? 1 : $next;
        $cursor->save();
    }
}
