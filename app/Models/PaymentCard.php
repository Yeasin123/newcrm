<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-attempt card/bank snapshot for a payment. Legacy `payment_cards`.
 * FK column `payment_histroy_id` is misspelled in the real schema — keep it.
 */
class PaymentCard extends Model
{
    protected $table = 'payment_cards';

    protected $fillable = [
        'payment_histroy_id', 'client_id', 'card_number', 'expMonth', 'expYear',
        'attemt', 'payment_date', 'routingNumber', 'accountNumber',
        'bankAccountName', 'bankAccountType', 'echeckType',
    ];

    public function paymentHistory(): BelongsTo
    {
        return $this->belongsTo(PaymentHistory::class, 'payment_histroy_id');
    }
}
