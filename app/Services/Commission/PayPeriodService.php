<?php

namespace App\Services\Commission;

use Illuminate\Support\Carbon;

/**
 * Fixed-length (default 14-day) pay periods anchored to a known start date.
 * `current()` / `next()` / `forDate()` return the window plus its payday,
 * which is `payday_offset_days` after the period ends.
 */
class PayPeriodService
{
    private Carbon $anchor;

    private int $length;

    private int $paydayOffset;

    public function __construct()
    {
        $this->anchor = Carbon::parse(config('commission.pay_period.anchor'))->startOfDay();
        $this->length = max(1, (int) config('commission.pay_period.length_days', 14));
        $this->paydayOffset = (int) config('commission.pay_period.payday_offset_days', 2);
    }

    /**
     * @return array{start:Carbon, end:Carbon, payday:Carbon, index:int}
     */
    public function forDate(Carbon $on): array
    {
        $daysFromAnchor = $this->anchor->diffInDays($on->copy()->startOfDay(), false);
        $index = (int) floor($daysFromAnchor / $this->length);

        $start = $this->anchor->copy()->addDays($index * $this->length);
        $end = $start->copy()->addDays($this->length - 1)->endOfDay();
        $payday = $start->copy()->addDays($this->length - 1 + $this->paydayOffset)->startOfDay();

        return ['start' => $start, 'end' => $end, 'payday' => $payday, 'index' => $index];
    }

    /** @return array{start:Carbon, end:Carbon, payday:Carbon, index:int} */
    public function current(): array
    {
        return $this->forDate(Carbon::now());
    }

    /** @return array{start:Carbon, end:Carbon, payday:Carbon, index:int} */
    public function next(): array
    {
        return $this->forDate(Carbon::now()->addDays($this->length));
    }

    /** @return array{start:Carbon, end:Carbon, payday:Carbon, index:int} */
    public function previous(): array
    {
        return $this->forDate(Carbon::now()->subDays($this->length));
    }
}
