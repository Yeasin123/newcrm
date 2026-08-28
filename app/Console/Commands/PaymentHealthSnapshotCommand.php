<?php

namespace App\Console\Commands;

use App\Services\Billing\PaymentHealthReport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PaymentHealthSnapshotCommand extends Command
{
    protected $signature = 'payment-health:snapshot {month? : YYYY-MM (defaults to last month)}';

    protected $description = 'Persist a payment_health_snapshots row for the given month';

    public function handle(PaymentHealthReport $report): int
    {
        $month = $this->argument('month')
            ? Carbon::parse($this->argument('month').'-01')
            : now()->subMonthNoOverflow()->startOfMonth();

        $snapshot = $report->persist($month);

        $this->info("Snapshot saved for {$month->format('Y-m')} (net \${$snapshot->net_amount}).");

        return self::SUCCESS;
    }
}
