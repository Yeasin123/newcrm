<?php

namespace App\Observers;

use App\Models\PaymentHistory;
use App\Services\Analytics\AnalyticsSyncService;

/** Keeps analytics_payments in sync (Phase 10c). No-op unless the mirror is enabled. */
class PaymentHistoryObserver
{
    public function __construct(private readonly AnalyticsSyncService $analytics) {}

    public function created(PaymentHistory $payment): void
    {
        $this->analytics->syncPayment($payment);
    }

    public function updated(PaymentHistory $payment): void
    {
        $this->analytics->syncPayment($payment);
    }

    public function deleted(PaymentHistory $payment): void
    {
        $this->analytics->deletePayment($payment);
    }

    public function restored(PaymentHistory $payment): void
    {
        $this->analytics->syncPayment($payment);
    }
}
