<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Legacy `claim_closed_details` — the closing summary for a claim. */
class ClaimClosedDetail extends Model
{
    use SoftDeletes;

    protected $table = 'claim_closed_details';

    protected $fillable = [
        'claim_id', 'technician_id', 'dispatch_amount', 'part_name',
        'part_cost', 'currency', 'notes', 'claim_spent_price',
    ];

    protected function casts(): array
    {
        return [
            'claim_spent_price' => 'float',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'technician_id');
    }
}
