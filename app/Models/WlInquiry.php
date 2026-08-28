<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-step funnel progress snapshot for the white-label checkout. Legacy
 * `wl_inqueries` (table spelling kept). `step` is the furthest step the
 * visitor has reached (1 zip … 8 paid).
 */
class WlInquiry extends Model
{
    protected $table = 'wl_inqueries';

    protected $fillable = [
        'first_name', 'last_name', 'zip_code', 'email', 'step', 'contact',
        'package_id', 'property_size', 'property_type', 'addons', 'address',
        'promo_name', 'payment_term',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
