<?php

/*
|--------------------------------------------------------------------------
| Salesman commission & payroll
|--------------------------------------------------------------------------
| Drives the Salesmen module's commission tracker. All figures are computed
| live from `sale_commissions`; these knobs only shape the pay-period window
| and the optional per-card spiff.
*/

return [

    // Headline commission rate shown on the salesman header ("8% rate").
    // Commission amounts themselves come from sale_commissions.commision_price
    // (ported formula) — this is display + the fallback estimate only.
    'rate' => (float) env('COMMISSION_RATE', 0.08),

    'pay_period' => [
        // Any date that was the FIRST day of a real pay period. Every
        // 'length_days' block forward/back from here is a pay period.
        'anchor' => env('COMMISSION_PAY_ANCHOR', '2026-08-22'),
        'length_days' => (int) env('COMMISSION_PAY_LENGTH_DAYS', 14),
        // Payday lands this many days after the period ends.
        'payday_offset_days' => (int) env('COMMISSION_PAYDAY_OFFSET_DAYS', 2),
        'label' => env('COMMISSION_PAY_LABEL', 'Bi-weekly'),
    ],

    // Optional "Spliffy" spiff — a flat bonus per prepaid / card deal in the
    // period. 0 by default (no fabricated money); set to turn it on.
    'spiff_per_card' => (float) env('COMMISSION_SPIFF_PER_CARD', 0),

    /*
    | Editable via /commissions/settings (stored as a JSON blob in
    | billing_settings['commission_config']). These are the defaults.
    |
    | Per plan type the commission amount is a FIXED percentage of the plan
    | price — the base salesman rate below is retained for reference only and
    | is not used in the calculation.
    */
    'defaults' => [
        'plan_rates' => [
            'monthly' => 0.90,       // monthly price × 90%  (price − 10%)
            'yearly_full' => 0.099,  // plan total × 9.9%
            'yearly_inst' => 0.08,   // plan total × 8%
        ],
        'base_rate' => 8.0,          // % — reference only
        'overrides' => [],           // [user_id => rate %]
        'spiff' => [
            'enabled' => true,
            // Stacking tiers: hit `cards` full-sale cards in a pay period,
            // earn `amount`. 4+ properties on one card still counts as 1 card.
            'tiers' => [
                ['cards' => 3, 'amount' => 65],
                ['cards' => 6, 'amount' => 130],
            ],
        ],
        'billing_spliffy' => [
            'enabled' => true,
            'amount' => 200,         // weekly top-biller bonus
        ],
        'after_hours' => [
            'enabled' => true,
            'cutoff' => 19,          // 24-hr hour; sales at/after this earn ×
            'multiplier' => 1.5,
        ],
        // A one-off 2× commission window, or null. {date, start, end}
        'two_x_event' => null,
    ],

    // payment_structure / total_year -> plan label for the "Plans Sold" grid.
    'plan_labels' => [
        'monthly' => 'Monthly',
        '1' => '1-Year',
        '2' => '2-Year',
        '3' => 'VIP 3-Yr',
        '5' => 'VIP 6-Yr',
        'installment' => 'Installment',
    ],
];
