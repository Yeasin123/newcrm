<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Maps to the legacy `payment_histories` table.
 *
 *   - status / payment_status: 1 = paid, 0 = unpaid/failed (both fields exist)
 *   - claim_payment = 1:       service-call payment, not policy billing
 *   - is_custom = 1:           manual / one-off charge
 *   - payment_type = 1:        bank/ACH, otherwise card
 */
class PaymentHistory extends Model
{
    protected $table = 'payment_histories';

    protected $fillable = [
        'client_id', 'plan_id', 'purpose', 'instalment', 'amount', 'currency',
        'payment_date', 'status', 'payment_status', 'subscription_id',
        'transaction_id', 'refund_transaction_id', 'cancle_subscription',
        'card_number', 'expMonth', 'expYear',
        'attemt_number', 'attemt_date', 'payment_s_date',
        'claim_payment', 'claim_id', 'refund_claim', 'refund_payment',
        'refund_date', 'refund_amount', 'payment_type', 'bank_info',
        'disputed', 'win', 'loss', 'color', 'authorize_account_id', 'is_custom',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'refund_amount' => 'float',
            'claim_payment' => 'boolean',
            'is_custom' => 'boolean',
            'disputed' => 'boolean',
            'win' => 'boolean',
            'loss' => 'boolean',
            'cancle_subscription' => 'boolean',
        ];
    }

    public function userInfo(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function clientClaim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }

    public function authorizeAccount(): BelongsTo
    {
        return $this->belongsTo(AuthorizeAccount::class, 'authorize_account_id');
    }

    public function paymentCards(): HasMany
    {
        return $this->hasMany(PaymentCard::class, 'payment_histroy_id');
    }

    public function isPaid(): bool
    {
        return (int) $this->payment_status === 1 || (int) $this->status === 1;
    }

    /**
     * A payment can be voided only until 21:05:00 on its creation date;
     * after that it must be refunded. Ported verbatim.
     */
    public function getCanVoidAttribute(): bool
    {
        if ($this->created_at === null) {
            return false;
        }

        $cutoff = Carbon::parse($this->created_at->format('Y-m-d').' 21:05:00');

        return now()->lessThanOrEqualTo($cutoff);
    }

    public function getCanRefundAttribute(): bool
    {
        return ! $this->can_void;
    }
}
