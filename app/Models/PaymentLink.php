<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tokenised payment/claim link. Legacy `payment_links`.
 * type: 1 = client, 2 = claim.  status: 1 = active, 0 = expired.
 */
class PaymentLink extends Model
{
    use SoftDeletes;

    protected $table = 'payment_links';

    protected $fillable = ['claim_id', 'client_id', 'link', 'type', 'token', 'status'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
