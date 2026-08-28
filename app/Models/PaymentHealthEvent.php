<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `payment_health_events` — a notable billing event needing attention. status: open|resolved. */
class PaymentHealthEvent extends Model
{
    protected $table = 'payment_health_events';

    protected $fillable = [
        'client_id', 'payment_history_id', 'authorize_account_id',
        'event_type', 'status', 'title', 'description', 'payload', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function paymentHistory(): BelongsTo
    {
        return $this->belongsTo(PaymentHistory::class, 'payment_history_id');
    }
}
