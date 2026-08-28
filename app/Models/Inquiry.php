<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A checkout-funnel inquiry — one row per visitor who enters the funnel,
 * carried alongside the `lead_details` row and advanced through
 * `progress_bar` as the visitor moves between steps. Legacy `inquiries`.
 */
class Inquiry extends Model
{
    use SoftDeletes;

    protected $table = 'inquiries';

    protected $fillable = [
        'lead_id', 'first_name', 'last_name', 'contact', 'email', 'status', 'shop',
        'zip_code', 'state', 'city', 'address', 'property_size', 'property_type',
        'package_id', 'progress_bar', 'addons',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadDetail::class, 'lead_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
