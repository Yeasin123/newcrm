<?php

namespace App\Services\Leads;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lead / campaign roll-up counts. `lead_details` holds ~6M rows, so the
 * cross-campaign breakdown is a single GROUP BY, computed once and cached
 * hard (15 min) — every campaign card AND the per-campaign KPI header read
 * from that one payload, so drilling into a campaign costs no extra query.
 * A user hitting "Update Leads" clears the cache and pays the recompute.
 *
 * Cached values are plain arrays (never Eloquent/Collection objects) so the
 * file / database cache store round-trips them cleanly.
 *
 * Legacy `leads_status` ids used for the headline metrics:
 *   1 = Sale · 9 = Call Back · 11 = No Answer · 4 = Spoke With
 *   2 = Has One · 3 = Doobie · 0 / null = No Status
 */
class CampaignStatsService
{
    private const KEY = 'leads:campaign-stats:v2';

    private const TTL = 900;

    public const SALE = 1;

    public const CALL_BACK = 9;

    public const NO_ANSWER = 11;

    public const SPOKE_WITH = 4;

    public const HAS_ONE = 2;

    public const DOOBIE = 3;

    /**
     * CRM-wide totals for the Leads & Campaigns header.
     *
     * @return array{campaigns:int, leads:int, sale:int, call_back:int, no_status:int}
     */
    public function global(): array
    {
        return Cache::remember(self::KEY.':global', self::TTL, function () {
            // Fold the per-campaign breakdown (one GROUP BY, shared with every
            // card) rather than scanning the 6M-row table a second time.
            $per = $this->perCampaign();

            return [
                'campaigns' => (int) DB::table('campaign_files')->whereNull('deleted_at')->count(),
                'leads' => array_sum(array_column($per, 'total')),
                'sale' => array_sum(array_column($per, 'sale')),
                'call_back' => array_sum(array_column($per, 'call_back')),
                'no_status' => array_sum(array_column($per, 'no_status')),
            ];
        });
    }

    /**
     * Per-campaign breakdown as a plain array keyed by campaign_id.
     *
     * @return array<int, array{total:int, sale:int, call_back:int, no_answer:int, spoke_with:int, has_one:int, doobie:int, no_status:int, conv:int}>
     */
    public function perCampaign(): array
    {
        return Cache::remember(self::KEY.':per-campaign', self::TTL, function () {
            $rows = DB::table('lead_details')->whereNull('deleted_at')
                ->select('campaign_id', 'status', DB::raw('count(*) as c'))
                ->groupBy('campaign_id', 'status')
                ->get();

            $out = [];
            foreach ($rows->groupBy('campaign_id') as $campaignId => $group) {
                $byStatus = [];
                foreach ($group as $row) {
                    $byStatus[$row->status] = (int) $row->c;
                }
                $out[(int) $campaignId] = $this->shape($byStatus);
            }

            return $out;
        });
    }

    /** Full status map for one campaign — served from the perCampaign() cache. */
    public function forCampaign(int $campaignId): array
    {
        return $this->perCampaign()[$campaignId] ?? $this->shape($this->freshCampaign($campaignId));
    }

    /** @return array<int|string,int> */
    private function freshCampaign(int $campaignId): array
    {
        return DB::table('lead_details')->whereNull('deleted_at')
            ->where('campaign_id', $campaignId)
            ->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /** @param array<int|string,int> $byStatus */
    private function shape(array $byStatus): array
    {
        $total = (int) array_sum($byStatus);
        $sale = (int) ($byStatus[self::SALE] ?? 0);

        return [
            'total' => $total,
            'sale' => $sale,
            'call_back' => (int) ($byStatus[self::CALL_BACK] ?? 0),
            'no_answer' => (int) ($byStatus[self::NO_ANSWER] ?? 0),
            'spoke_with' => (int) ($byStatus[self::SPOKE_WITH] ?? 0),
            'has_one' => (int) ($byStatus[self::HAS_ONE] ?? 0),
            'doobie' => (int) ($byStatus[self::DOOBIE] ?? 0),
            'no_status' => (int) (($byStatus[0] ?? 0) + ($byStatus[''] ?? 0)),
            'conv' => $total > 0 ? (int) round($sale / $total * 100) : 0,
        ];
    }

    public function flush(): void
    {
        Cache::forget(self::KEY.':global');
        Cache::forget(self::KEY.':per-campaign');
    }

    public function flushCampaign(int $campaignId): void
    {
        $this->flush();
    }
}
