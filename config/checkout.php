<?php

/*
|--------------------------------------------------------------------------
| Public checkout funnel (Phase 12b)
|--------------------------------------------------------------------------
| Config-driven constants for the consolidated white-label / shop plan
| checkout funnel. The three legacy controllers (PlanCheckoutController,
| AdditionalCheckoutController, Web\ShopPlansController) all hard-coded
| these; they are gathered here so the one modern funnel matches the
| current live behaviour of the WL flow (the newest of the three).
*/

return [

    // Payment-term code -> [price field, year multiplier, ARB interval months].
    // Legacy PlanCheckoutController::priceCalculator: 1 = monthly, 2 = 1yr,
    // 4 = 3yr, 6 = 5yr. Only the monthly term creates an ARB subscription;
    // the multi-year terms are a single prepaid charge (yearly path, no ARB).
    'terms' => [
        1 => ['field' => 'monthly', 'multiplier' => 1, 'label' => 'Monthly',        'structure' => 1, 'recurring' => true],
        2 => ['field' => 'yearly',  'multiplier' => 1, 'label' => '1 Year',          'structure' => 2, 'recurring' => false],
        4 => ['field' => 'yearly',  'multiplier' => 3, 'label' => '3 Years',         'structure' => 2, 'recurring' => false],
        6 => ['field' => 'yearly',  'multiplier' => 5, 'label' => '5 Years',         'structure' => 2, 'recurring' => false],
    ],

    // clients.end_date offset for the prepaid multi-year terms
    // (legacy checkoutstepFinal: term 2 -> +15 months, 4 -> +41, 6 -> +68).
    'prepaid_end_months' => [
        2 => 15,
        4 => 41,
        6 => 68,
    ],

    // Discount type codes shared with the `promotions.promotion_type` column
    // and the legacy priceCalculator $discountType argument.
    'discount_type' => [
        'fixed' => 1,
        'percent' => 2,
    ],

    // The six add-ons the WL funnel offers, in display order
    // (legacy PlanCheckoutController: $addonIds = [69, 50, 47, 71, 60, 40]).
    'addon_ids' => [69, 50, 47, 71, 60, 40],

    // The WL funnel always prices against property size / type row 1 (legacy
    // PlanCheckoutController::calculatePrice hard-codes $propertySize = 1,
    // $propertyType = 1); whatever those rows cost per term is added to the
    // subtotal.
    'property_size_id' => 1,
    'property_type_id' => 1,

    // Fixed values the WL funnel stamps on every new client / lead.
    'lead_campaign_id' => 99,
    'lead_type' => 'Shop',
    'lead_tag' => 'Inquiry',
    'client_type' => 'WL',
    'call_fee' => 85,

    // Outbound Zapier lead mirror (legacy wlUserinfo). Off by default — the
    // codebase routes every outbound webhook through a config flag.
    'zapier_lead_url' => env('CHECKOUT_ZAPIER_LEAD_URL', ''),
];
