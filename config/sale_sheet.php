<?php

/*
|--------------------------------------------------------------------------
| Sale Sheet — admin "Add Customer" wizard (Phase 12e)
|--------------------------------------------------------------------------
| The staff-operated new-policy flow. Its pricing rules come from
| Admin\ClientsController::priceCalculator, which differs from the public
| funnel (config/checkout.php): the package price is read straight off the
| `packages` catalog columns, the multi-year multiplier is `term - 1`, the
| discount is percentage-only, property size/type rows priced in `%`
| currency are a share of the package price, and an installment split is
| supported.
*/

return [

    // Payment-term code => [price field, multiplier, structure, recurring,
    // clients.end_date offset months for the prepaid multi-year terms].
    // 1 = monthly, 2 = 1yr, 3 = 2yr, 4 = 3yr, 6 = 5yr (legacy clientAddPost).
    'terms' => [
        1 => ['field' => 'monthly', 'multiplier' => 1, 'structure' => 1, 'recurring' => true,  'end_months' => null, 'label' => 'Monthly'],
        2 => ['field' => 'yearly',  'multiplier' => 1, 'structure' => 2, 'recurring' => true,  'end_months' => 14,   'label' => '1 Year (2 Months Free)'],
        3 => ['field' => 'yearly',  'multiplier' => 2, 'structure' => 2, 'recurring' => false, 'end_months' => 28,   'label' => '2 Year (4 Months Free)'],
        4 => ['field' => 'yearly',  'multiplier' => 3, 'structure' => 2, 'recurring' => false, 'end_months' => 42,   'label' => '3 Year (6 Months Free)'],
        6 => ['field' => 'yearly',  'multiplier' => 5, 'structure' => 2, 'recurring' => false, 'end_months' => 72,   'label' => '6 Year'],
    ],

    // Installment split codes (legacy $installment_type): the pre-discount
    // total is divided into this many equal payments.
    'installment_splits' => [2, 3, 4, 5],

    // Discount quick-pick percentages (shown when "Apply Discount? = Yes").
    'discount_percents' => [5, 10, 15, 20, 25, 30, 35, 40],

    // Plan pre-selected on a fresh property block (null => first package).
    'default_package_id' => env('SALE_SHEET_DEFAULT_PACKAGE_ID'),

    // Service-call-fee quick-pick buttons.
    'call_fees' => [45, 55, 65, 75, 85, 95, 100, 125],
    'default_call_fee' => 75,

    // Bank-account types offered on the eCheck payment method.
    'bank_account_types' => ['Checking', 'Savings', 'Business Checking'],
];
