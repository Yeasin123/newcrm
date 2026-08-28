<?php

namespace App\Services\Commission;

use App\Models\SaleCommission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Commission workflow. Mirrors the legacy `NewSalesmanCommissionController`
 * "mark paid" flow: flipping `is_paid` on `sale_commissions` rows.
 */
class CommissionService
{
    /** Mark a set of commission rows as paid. Returns the count updated. */
    public function markPaid(Collection|array $commissions): int
    {
        $ids = collect($commissions)
            ->map(fn ($c) => $c instanceof SaleCommission ? $c->id : (int) $c)
            ->filter()
            ->all();

        if ($ids === []) {
            return 0;
        }

        return SaleCommission::whereIn('id', $ids)
            ->where('is_paid', 0)
            ->where('is_cancel', 0)
            ->update(['is_paid' => 1]);
    }

    public function markUnpaid(SaleCommission $commission): void
    {
        $commission->update(['is_paid' => 0]);
    }

    /**
     * Totals for a filtered commission query.
     *
     * @return array{count:int, gross:float, paid:float, unpaid:float, cancelled:float}
     */
    public function totals(Builder $query): array
    {
        $base = (clone $query);

        return [
            'count' => (clone $base)->count(),
            'gross' => (float) (clone $base)->sum('commision_price'),
            'paid' => (float) (clone $base)->where('is_paid', 1)->sum('commision_price'),
            'unpaid' => (float) (clone $base)->where('is_paid', 0)->where('is_cancel', 0)->sum('commision_price'),
            'cancelled' => (float) (clone $base)->where('is_cancel', 1)->sum('commision_price'),
        ];
    }
}
