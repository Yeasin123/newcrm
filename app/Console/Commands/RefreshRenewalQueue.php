<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\RenewalClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Ported from the legacy `renewal:client` command: for every yearly
 * (payment_structure = 2) client whose effective coverage end date
 * (custom_e_date ?? end_date) is exactly two months out, add them to the
 * renewal call queue.
 */
class RefreshRenewalQueue extends Command
{
    protected $signature = 'renewal:client';

    protected $description = 'Add yearly clients due to renew in ~2 months to the renewal_clients queue';

    public function handle(): int
    {
        $twoMonthsOut = Carbon::today()->addMonths(2);
        $added = 0;

        Client::query()
            ->where('payment_structure', 2)
            ->select('id', 'custom_e_date', 'end_date')
            ->chunkById(500, function ($clients) use ($twoMonthsOut, &$added) {
                foreach ($clients as $client) {
                    $endDate = $client->custom_e_date ?: $client->end_date;

                    if (! $endDate) {
                        continue;
                    }

                    try {
                        if (Carbon::parse($endDate)->isSameDay($twoMonthsOut)) {
                            RenewalClient::firstOrCreate(['client_id' => $client->id]);
                            $added++;
                        }
                    } catch (\Throwable $e) {
                        // unparseable date string — skip
                    }
                }
            });

        $this->info("Renewal queue refreshed. {$added} client(s) added.");

        return self::SUCCESS;
    }
}
