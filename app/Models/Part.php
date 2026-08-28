<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A part ordered for a claim. Legacy `parts` — keep the misspelled
 * `purchaes_form` column. `who_buy`: 1 = AHC, else technician.
 */
class Part extends Model
{
    protected $table = 'parts';

    protected $fillable = [
        'claim_id', 'name', 'price', 'model', 'purchaes_form', 'order_number',
        'tracking_number', 'labor_price', 'who_buy', 'es_arrival',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'labor_price' => 'float',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }
}
