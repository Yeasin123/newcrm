<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A salesman commission line. Legacy `sale_commissions` — keep the misspelled
 * `commision_price` column. `type` can be null (a policy commission) or
 * 'bonus'. `store_date` / `total_year` are strings.
 */
class SaleCommission extends Model
{
    use HasFactory;

    protected $table = 'sale_commissions';

    protected $fillable = [
        'user_id', 'client_id', 'policy_number', 'payment_structure',
        'installment', 'price', 'commision_price', 'store_date', 'total_year',
        'type', 'is_paid', 'is_cancel', 'is_cancel_deducted',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'commision_price' => 'float',
            'is_paid' => 'boolean',
            'is_cancel' => 'boolean',
            'is_cancel_deducted' => 'boolean',
            'store_date' => 'datetime',
        ];
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function scopePaid(Builder $q): Builder
    {
        return $q->where('is_paid', 1);
    }

    public function scopeUnpaid(Builder $q): Builder
    {
        return $q->where('is_paid', 0)->where('is_cancel', 0);
    }

    public function scopeCancelled(Builder $q): Builder
    {
        return $q->where('is_cancel', 1);
    }
}
