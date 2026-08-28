<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\RenewalClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Ported from the legacy `renewal:client-delete` command. Removes queue rows
 * that no longer belong there: the client is gone / soft-deleted, no longer
 * yearly, already renewed (`renewed = 1`), or their coverage end date has
 * moved well past the ~2-month window (renewed elsewhere or lapsed).
 *
 * The legacy version ran every 5 minutes and was flagged risky — this one
 * only ever deletes from `renewal_clients` (never from `clients`).
 */
class PruneRenewalQueue extends Command
{
    protected $signature = 'renewal:client-delete';

    protected $description = 'Drop no-longer-eligible rows from the renewal_clients queue';

    public function handle(): int
    {
        $removed = 0;

        RenewalClient::query()->with('client')->chunkById(500, function ($rows) use (&$removed) {
            foreach ($rows as $row) {
                $client = $row->client;

                $ineligible = ! $client
                    || (int) $client->payment_structure !== 2
                    || (int) $client->renewed === 1;

                if (! $ineligible && $client) {
                    $endDate = $client->custom_e_date ?: $client->end_date;
                    if ($endDate) {
                        try {
                            // > ~10 days past the 2-month mark either way → out of window.
                            $ineligible = Carbon::parse($endDate)->diffInDays(Carbon::today()->addMonths(2), false) > 10
                                || Carbon::parse($endDate)->isPast();
                        } catch (\Throwable $e) {
                            // keep it — can't judge an unparseable date
                        }
                    }
                }

                if ($ineligible) {
                    $row->delete();
                    $removed++;
                }
            }
        });

        $this->info("Renewal queue pruned. {$removed} row(s) removed.");

        return self::SUCCESS;
    }
}
