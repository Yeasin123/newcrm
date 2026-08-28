<?php

namespace App\Services\Lead;

use App\Models\LeadAiScore;
use App\Models\LeadBlastItem;
use App\Models\LeadBlastRun;
use App\Models\LeadDetail;
use App\Models\LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Blast Hub generator. Ported from the legacy App\Services\LeadBlasterService:
 * build the eligible-lead query from the chosen filters (blast type → lead
 * status, carrier, campaign, min AI score, cell-phone only, not currently
 * locked, optionally not DNC), snapshot the counts + selection into
 * lead_blast_runs / lead_blast_items, then on CSV download hold every selected
 * lead out of future runs for 28 days.
 *
 * The only deviations from the legacy source are portability fixes: `""` MySQL
 * string literals became `''`, and the DNC phone-normalisation expression has
 * a sqlite branch (the app itself always runs on MySQL).
 */
class LeadBlasterService
{
    /** @var array<string,bool> */
    private array $columnCache = [];

    public function generateRun(array $filters): LeadBlastRun
    {
        $filters['min_ai_score'] = ($filters['min_ai_score'] ?? null) === 'any'
            ? null
            : ($filters['min_ai_score'] ?? null);

        $requestedCount = (string) ($filters['lead_count'] ?? '50000');
        $limit = $requestedCount === 'all' ? null : min((int) $requestedCount, 500000);

        return DB::transaction(function () use ($filters, $requestedCount, $limit) {
            $baseNoCell = $this->baseLeadQuery($filters);
            $cellNoLockNoDnc = $this->applyCellPhoneFilter(clone $baseNoCell);

            $excludedNonCell = max(0, (clone $baseNoCell)->count() - (clone $cellNoLockNoDnc)->count());

            $afterLock = $this->applyLockExclusion(clone $cellNoLockNoDnc);
            $excludedLocked = max(0, (clone $cellNoLockNoDnc)->count() - (clone $afterLock)->count());

            $finalQuery = clone $afterLock;
            $excludedDnc = 0;

            if (! empty($filters['dnc_excluded'])) {
                $afterDnc = $this->applyDncExclusion(clone $afterLock);
                $excludedDnc = max(0, (clone $afterLock)->count() - (clone $afterDnc)->count());
                $finalQuery = $afterDnc;
            }

            $totalEligible = (clone $finalQuery)->count();

            $run = LeadBlastRun::create([
                'user_id' => auth()->guard('web')->id(),
                'blast_type' => $filters['blast_type'],
                'carrier_filter' => $filters['carrier_filter'],
                'campaign_id' => $filters['campaign_id'] ?? null,
                'requested_count' => $requestedCount,
                'min_ai_score' => $filters['min_ai_score'] !== null ? (int) $filters['min_ai_score'] : null,
                'dnc_excluded' => ! empty($filters['dnc_excluded']),
                'total_eligible' => $totalEligible,
                'excluded_locked_count' => $excludedLocked,
                'excluded_dnc_count' => $excludedDnc,
                'excluded_non_cell_count' => $excludedNonCell,
                'status' => 'generated',
            ]);

            $this->insertRunItems($run, $finalQuery, $limit);

            $run->selected_count = $run->items()->count();
            $run->save();

            return $run->fresh(['campaign']);
        });
    }

    public function buildEligibleQuery(array $filters): Builder
    {
        $query = $this->applyCellPhoneFilter($this->baseLeadQuery($filters));
        $query = $this->applyLockExclusion($query);

        if (! empty($filters['dnc_excluded'])) {
            $query = $this->applyDncExclusion($query);
        }

        return $query;
    }

    private function baseLeadQuery(array $filters): Builder
    {
        $query = LeadDetail::query()
            ->leftJoin('lead_ai_scores', 'lead_ai_scores.lead_id', '=', 'lead_details.id')
            ->select('lead_details.*');

        $this->applyBlastTypeFilter($query, $filters['blast_type']);
        $this->applyCarrierFilter($query, $filters['carrier_filter']);
        $this->applyCampaignFilter($query, $filters['campaign_id'] ?? null);
        $this->applyAiScoreFilter($query, $filters['min_ai_score'] ?? null);

        return $query;
    }

    public function applyBlastTypeFilter(Builder $query, string $blastType): Builder
    {
        $statusColumn = $this->detectLeadStatusColumn();

        if ($blastType === 'cold') {
            $noStatusIds = $this->statusIdsLike(['no status', 'none', 'new']);

            return $query->where(function ($statusQuery) use ($statusColumn, $noStatusIds) {
                $statusQuery->whereNull("lead_details.{$statusColumn}")
                    ->orWhere("lead_details.{$statusColumn}", 0);

                if (! empty($noStatusIds)) {
                    $statusQuery->orWhereIn("lead_details.{$statusColumn}", $noStatusIds);
                }
            });
        }

        $statusMap = [
            'warm' => ['no answer', 'not answered', 'no answered', 'voicemail', 'callback', 'call back'],
            'hot' => ['interested', 'qualified', 'follow up', 'follow-up'],
        ];

        $statusIds = $this->statusIdsLike($statusMap[$blastType] ?? []);

        return empty($statusIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn("lead_details.{$statusColumn}", $statusIds);
    }

    public function applyCarrierFilter(Builder $query, string $carrierFilter): Builder
    {
        if ($carrierFilter === 'all') {
            return $query;
        }

        $tmobileSql = "LOWER(REPLACE(REPLACE(COALESCE(lead_details.phone_company, ''), '-', ''), ' ', '')) LIKE ?";
        $tmobileValue = '%tmobile%';

        if ($carrierFilter === 'tmobile_only') {
            return $query->whereRaw($tmobileSql, [$tmobileValue]);
        }

        if ($carrierFilter === 'exclude_tmobile') {
            return $query->whereRaw("NOT ({$tmobileSql})", [$tmobileValue]);
        }

        return $query;
    }

    public function applyCampaignFilter(Builder $query, $campaignId): Builder
    {
        if ($campaignId) {
            $query->where('lead_details.campaign_id', $campaignId);
        }

        return $query;
    }

    public function applyDncExclusion(Builder $query): Builder
    {
        if (! Schema::hasTable('dnc_numbers')) {
            return $query;
        }

        $phoneExpression = $this->phoneNormalizeSqlExpression('lead_details.contact');

        return $query->whereNotExists(function ($dncQuery) use ($phoneExpression) {
            $dncQuery->select(DB::raw(1))
                ->from('dnc_numbers')
                ->whereRaw("dnc_numbers.phone_normalized = {$phoneExpression}");
        });
    }

    public function applyLockExclusion(Builder $query): Builder
    {
        $now = now();

        if (Schema::hasTable('lead_blast_items')) {
            $query->whereNotExists(function ($lockQuery) use ($now) {
                $lockQuery->select(DB::raw(1))
                    ->from('lead_blast_items')
                    ->whereColumn('lead_blast_items.lead_id', 'lead_details.id')
                    ->where('lead_blast_items.locked_until', '>', $now);
            });
        }

        if ($this->leadHasColumn('is_download')) {
            $query->where(function ($downloadQuery) {
                $downloadQuery->whereNull('lead_details.is_download')
                    ->orWhere('lead_details.is_download', '!=', 1);
            });
        }

        if ($this->leadHasColumn('lock_lead') && $this->leadHasColumn('lock_time')) {
            $query->where(function ($lockQuery) use ($now) {
                $lockQuery->whereNull('lead_details.lock_lead')
                    ->orWhere('lead_details.lock_lead', '!=', 'lock')
                    ->orWhereNull('lead_details.lock_time')
                    ->orWhere('lead_details.lock_time', '<=', $now);
            });
        }

        return $query;
    }

    public function applyAiScoreFilter(Builder $query, $minScore): Builder
    {
        if ($minScore === null || $minScore === '' || $minScore === 'any') {
            return $query;
        }

        return $query->where('lead_ai_scores.score', '>=', (int) $minScore);
    }

    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    public function createCsv(LeadBlastRun $run): string
    {
        if ($run->csv_path && Storage::disk('local')->exists($run->csv_path)) {
            return $run->csv_path;
        }

        $dir = 'lead-blaster';
        Storage::disk('local')->makeDirectory($dir);

        $path = "{$dir}/run-{$run->id}.csv";
        $absolutePath = Storage::disk('local')->path($path);
        $handle = fopen($absolutePath, 'w');

        fputcsv($handle, ['first_name', 'last_name', 'phone', 'email', 'carrier', 'campaign', 'status', 'ai_score']);

        LeadBlastItem::query()
            ->where('lead_blast_run_id', $run->id)
            ->join('lead_details', 'lead_details.id', '=', 'lead_blast_items.lead_id')
            ->leftJoin('campaign_files', 'campaign_files.id', '=', 'lead_details.campaign_id')
            ->leftJoin('leads_status', 'leads_status.id', '=', 'lead_details.status')
            ->select([
                'lead_details.first_name',
                'lead_details.last_name',
                'lead_details.contact',
                'lead_details.email',
                'lead_details.phone_company',
                'campaign_files.name as campaign_name',
                'leads_status.title as status_title',
                'lead_blast_items.ai_score',
            ])
            ->orderBy('lead_blast_items.id')
            ->chunk(2000, function ($items) use ($handle) {
                foreach ($items as $item) {
                    fputcsv($handle, [
                        $item->first_name,
                        $item->last_name,
                        $item->contact,
                        $item->email,
                        $item->phone_company,
                        $item->campaign_name,
                        $item->status_title ?: 'No Status',
                        $item->ai_score,
                    ]);
                }
            });

        fclose($handle);

        $run->update(['csv_path' => $path]);

        return $path;
    }

    public function lockRunItems(LeadBlastRun $run): void
    {
        $lockedUntil = now()->addDays(28);
        $now = now();

        DB::transaction(function () use ($run, $lockedUntil, $now) {
            LeadBlastItem::where('lead_blast_run_id', $run->id)->update([
                'downloaded_at' => $now,
                'locked_until' => $lockedUntil,
                'updated_at' => $now,
            ]);

            $leadIds = LeadBlastItem::where('lead_blast_run_id', $run->id)->pluck('lead_id')->all();
            $updates = [];

            if ($this->leadHasColumn('is_download')) {
                $updates['is_download'] = 1;
            }

            if ($this->leadHasColumn('lock_lead')) {
                $updates['lock_lead'] = 'lock';
            }

            if ($this->leadHasColumn('lock_time')) {
                $updates['lock_time'] = $lockedUntil->format('Y-m-d H:i:s');
            }

            if (! empty($updates)) {
                foreach (array_chunk($leadIds, 1000) as $chunk) {
                    LeadDetail::whereIn('id', $chunk)->update($updates);
                }
            }

            $run->update(['status' => 'downloaded']);
        });
    }

    public function detectLeadStatusColumn(): string
    {
        return $this->leadHasColumn('status') ? 'status' : 'leads_status';
    }

    public function generateMissingScoresChunked(int $limit = 500): int
    {
        $inserted = 0;

        LeadDetail::query()
            ->leftJoin('lead_ai_scores', 'lead_ai_scores.lead_id', '=', 'lead_details.id')
            ->whereNull('lead_ai_scores.id')
            ->select('lead_details.id', 'lead_details.status')
            ->orderBy('lead_details.id')
            ->limit($limit)
            ->chunk(100, function ($leads) use (&$inserted) {
                $now = now();
                $rows = [];

                foreach ($leads as $lead) {
                    $rows[] = [
                        'lead_id' => $lead->id,
                        'score' => $this->fallbackScore($lead),
                        'reason' => 'Fallback score based on cached CRM status signals.',
                        'scored_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($rows)) {
                    LeadAiScore::insertOrIgnore($rows);
                    $inserted += count($rows);
                }
            });

        return $inserted;
    }

    private function insertRunItems(LeadBlastRun $run, Builder $query, ?int $limit): void
    {
        $selected = 0;
        $page = 1;
        $perPage = 2000;
        $now = now();

        $query->select([
            'lead_details.id',
            'lead_details.contact',
            DB::raw('lead_ai_scores.score as ai_score'),
        ])
            ->orderByRaw('COALESCE(lead_ai_scores.score, -1) DESC')
            ->orderBy('lead_details.id');

        do {
            $remaining = $limit === null ? $perPage : max(0, $limit - $selected);
            if ($remaining === 0) {
                break;
            }

            $take = min($perPage, $remaining);
            $leads = (clone $query)->skip(($page - 1) * $perPage)->take($take)->get();

            if ($leads->isEmpty()) {
                break;
            }

            $rows = [];

            foreach ($leads as $lead) {
                $rows[] = [
                    'lead_blast_run_id' => $run->id,
                    'lead_id' => $lead->id,
                    'phone_normalized' => $this->normalizePhone($lead->contact),
                    'ai_score' => $lead->ai_score,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 1000) as $chunk) {
                LeadBlastItem::insertOrIgnore($chunk);
            }

            $selected += $leads->count();
            $page++;
        } while ($limit === null || $selected < $limit);
    }

    private function applyCellPhoneFilter(Builder $query): Builder
    {
        return $query->whereRaw("LOWER(TRIM(COALESCE(lead_details.contact_text, ''))) = ?", ['cell phone']);
    }

    private function statusIdsLike(array $titles): array
    {
        if (empty($titles) || ! Schema::hasTable('leads_status')) {
            return [];
        }

        return LeadStatus::query()
            ->where(function ($query) use ($titles) {
                foreach ($titles as $title) {
                    $query->orWhereRaw('LOWER(TRIM(title)) = ?', [strtolower($title)])
                        ->orWhereRaw('LOWER(title) LIKE ?', ['%'.strtolower($title).'%']);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function fallbackScore($lead): int
    {
        return (int) ($lead->status ?? 0) > 0 ? 55 : 25;
    }

    private function phoneNormalizeSqlExpression(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // sqlite has no REGEXP_REPLACE — strip the common separators by hand.
            $stripped = $column;
            foreach (['-', ' ', '(', ')', '.', '+'] as $ch) {
                $stripped = "REPLACE({$stripped}, '{$ch}', '')";
            }
            $digits = "COALESCE({$stripped}, '')";

            return "CASE WHEN LENGTH({$digits}) = 11 AND SUBSTR({$digits}, 1, 1) = '1' THEN SUBSTR({$digits}, 2) ELSE {$digits} END";
        }

        $digits = "REGEXP_REPLACE(COALESCE({$column}, ''), '[^0-9]', '')";

        return "CASE WHEN CHAR_LENGTH({$digits}) = 11 AND LEFT({$digits}, 1) = '1' THEN SUBSTRING({$digits}, 2) ELSE {$digits} END";
    }

    private function leadHasColumn(string $column): bool
    {
        return $this->columnCache[$column] ??= Schema::hasColumn('lead_details', $column);
    }
}
