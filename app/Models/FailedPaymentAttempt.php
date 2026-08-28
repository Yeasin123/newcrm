<?php

namespace App\Models;

use App\Enums\FailedPaymentAttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedPaymentAttempt extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'is_automatic' => 'boolean',
    ];

    protected $attributes = [
        'status' => FailedPaymentAttemptStatus::RECORDED,
        'is_automatic' => false,
    ];

    public function failedPaymentCase()
    {
        return $this->belongsTo(FailedPaymentCase::class);
    }

    public function webhookEvent()
    {
        return $this->belongsTo(AnetWebhookEvent::class, 'anet_webhook_event_id', 'event_id');
    }

    public function paymentHistory()
    {
        return $this->belongsTo(PaymentHistory::class, 'payment_history_id');
    }
}
