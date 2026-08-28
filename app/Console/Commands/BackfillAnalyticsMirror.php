<?php

namespace App\Console\Commands;

use App\Models\Claim;
use App\Models\Client;
use App\Models\LeadDetail;
use App\Models\PaymentHistory;
use App\Services\Analytics\AnalyticsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Populate the analytics_* mirror from the existing CRM rows by replaying each
 * record through AnalyticsSyncService. Run once after enabling
 * ANALYTICS_MIRROR_ENABLED; the observers keep it current thereafter.
 */
class BackfillAnalyticsMirror extends Command
{
    protected $signature = 'analytics:backfill
        {--entity=all : customers|payments|claims|leads|all}
        {--since= : only rows created on/after this date (Y-m-d)}
        {--limit=0 : cap rows per entity (0 = no cap)}
        {--chunk=1000 : rows per batch}';

    protected $description = 'Backfill the analytics_* mirror tables from existing CRM records.';

    public function handle(AnalyticsSyncService $analytics): int
    {
        if (! $analytics->enabled()) {
            $this->error('Analytics mirror is disabled. Set ANALYTICS_MIRROR_ENABLED=true first.');

            return self::FAILURE;
        }

        $entity = strtolower((string) $this->option('entity'));
        $since = $this->option('since') ? Carbon::parse($this->option('since'))->startOfDay() : null;
        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(100, (int) $this->option('chunk'));

        $entities = $entity === 'all' ? ['customers', 'payments', 'claims', 'leads'] : [$entity];

        foreach ($entities as $name) {
            $this->backfill($name, $analytics, $since, $limit, $chunk);
        }

        return self::SUCCESS;
    }

    protected function backfill(string $name, AnalyticsSyncService $analytics, ?Carbon $since, int $limit, int $chunk): void
    {
        [$query, $sync] = match ($name) {
            'customers' => [Client::query(), fn ($m) => $analytics->syncClient($m)],
            'payments' => [PaymentHistory::query(), fn ($m) => $analytics->syncPayment($m)],
            'claims' => [Claim::query(), fn ($m) => $analytics->syncClaim($m)],
            'leads' => [LeadDetail::query(), fn ($m) => $analytics->syncLead($m)],
            default => [null, null],
        };

        if (! $query) {
            $this->warn("Unknown entity: {$name}");

            return;
        }

        $query->when($since, fn ($q) => $q->where('created_at', '>=', $since))->orderBy('id');

        $done = 0;
        $bar = $this->output->createProgressBar($limit ?: (clone $query)->count());
        $bar->start();

        $query->chunkById($chunk, function ($rows) use ($sync, $limit, &$done, $bar) {
            foreach ($rows as $row) {
                $sync($row);
                $done++;
                $bar->advance();

                if ($limit && $done >= $limit) {
                    return false;
                }
            }

            return true;
        });

        $bar->finish();
        $this->newLine();
        $this->info("{$name}: {$done} row(s) mirrored.");
    }
}
