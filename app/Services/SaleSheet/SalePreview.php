<?php

namespace App\Services\SaleSheet;

use Illuminate\Support\Carbon;

/**
 * The right-hand summary of a sale in progress: each property's price plus
 * the group roll-up shown in the Add Customer sidebar.
 */
readonly class SalePreview
{
    /**
     * @param  list<SalePrice>  $properties
     */
    public function __construct(
        public array $properties,
        public float $totalAllProperties,
        public float $totalChargedToday,
        public float $monthlyBilling,
        public Carbon $startDate,
    ) {}
}
