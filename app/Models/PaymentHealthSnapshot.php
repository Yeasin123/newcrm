<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Legacy `payment_health_snapshots` — one persisted monthly roll-up. */
class PaymentHealthSnapshot extends Model
{
    protected $table = 'payment_health_snapshots';

    protected $fillable = [
        'report_month', 'paid_count', 'failed_count', 'refund_count', 'dispute_count', 'renewal_count',
        'gross_amount', 'failed_amount', 'refund_amount', 'dispute_amount', 'net_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'report_month' => 'date',
            'gross_amount' => 'decimal:2',
            'failed_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'dispute_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }
}
