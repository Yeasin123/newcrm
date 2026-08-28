<?php

namespace App\Console\Commands;

use App\Services\Earnings\ClientEarningsCalculator;
use Illuminate\Console\Command;

/**
 * Ported from the legacy app (was registered but unscheduled — run manually
 * or from the Earnings screen's "Recalculate" action).
 */
class CalculateClientEarnings extends Command
{
    protected $signature = 'calculate:client-earnings';

    protected $description = 'Recalculate client_earnings totals (earn / profit) for all clients';

    public function handle(ClientEarningsCalculator $calculator): int
    {
        $this->info('Recalculating client earnings…');

        $n = $calculator->recalculateAll();

        $this->info("Done. {$n} clients processed.");

        return self::SUCCESS;
    }
}
