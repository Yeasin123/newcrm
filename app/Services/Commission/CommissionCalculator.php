<?php

namespace App\Services\Commission;

use App\Models\Client;
use Illuminate\Support\Carbon;

/**
 * The commission amount for one sale, applying the Commission Settings:
 * the fixed per-plan-type rate, the after-hours multiplier and a one-off
 * 2× window. This is what CheckoutService writes to
 * sale_commissions.commision_price (the legacy commission_double bonus is
 * still layered on by the caller).
 */
class CommissionCalculator
{
    public function __construct(private readonly CommissionSettingsService $settings) {}

    /**
     * @return array{amount:float, plan_type:string, rate:float, after_hours:bool, doubled:bool}
     */
    public function forSale(Client $client, float $saleAmount, ?Carbon $when = null): array
    {
        $when ??= Carbon::now();
        $type = $this->planType($client);

        $rate = (float) $this->settings->get("plan_rates.{$type}", match ($type) {
            'monthly' => 0.90,
            'yearly_inst' => 0.08,
            default => 0.099,
        });

        $amount = round($saleAmount * $rate, 2);

        $afterHours = false;
        $ah = $this->settings->get('after_hours', []);
        if (! empty($ah['enabled']) && $when->hour >= (int) ($ah['cutoff'] ?? 19)) {
            $amount = round($amount * max(1, (float) ($ah['multiplier'] ?? 1)), 2);
            $afterHours = true;
        }

        $doubled = false;
        if ($this->inTwoXWindow($when)) {
            $amount = round($amount * 2, 2);
            $doubled = true;
        }

        return [
            'amount' => $amount,
            'plan_type' => $type,
            'rate' => $rate,
            'after_hours' => $afterHours,
            'doubled' => $doubled,
        ];
    }

    public function planType(Client $client): string
    {
        if ((string) $client->payment_structure === '1') {
            return 'monthly';
        }

        return (int) ($client->installment ?? 0) >= 2 ? 'yearly_inst' : 'yearly_full';
    }

    private function inTwoXWindow(Carbon $when): bool
    {
        $e = $this->settings->get('two_x_event');

        if (! is_array($e) || empty($e['date'])) {
            return false;
        }

        try {
            $start = Carbon::parse($e['date'].' '.($e['start'] ?? '00:00'));
            $end = Carbon::parse($e['date'].' '.($e['end'] ?? '23:59'));

            return $when->betweenIncluded($start, $end);
        } catch (\Throwable) {
            return false;
        }
    }
}
