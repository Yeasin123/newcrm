<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Authorize.Net CIM customer/payment profile pointer. Legacy `card_profiles`.
 * Column `payment_histroy_id` is misspelled in the real schema — keep it.
 */
class CardProfile extends Model
{
    protected $table = 'card_profiles';

    protected $fillable = [
        'payment_histroy_id', 'client_id', 'authorize_account_id',
        'customer_profile_id', 'payment_profile_id', 'last4', 'brand',
        'expMonth', 'expYear', 'routingNumber', 'accountNumber',
        'bankAccountName', 'echeckType', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function authorizeAccount(): BelongsTo
    {
        return $this->belongsTo(AuthorizeAccount::class, 'authorize_account_id');
    }
}
