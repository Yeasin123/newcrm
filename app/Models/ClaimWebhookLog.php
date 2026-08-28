<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit row for every inbound claim-status webhook. Legacy `claim_webhook_logs`
 * (was defined but unused by the old app; the rebuild writes to it).
 */
class ClaimWebhookLog extends Model
{
    protected $table = 'claim_webhook_logs';

    protected $fillable = [
        'claim_id', 'claim_no', 'source', 'event', 'workflow_step', 'status',
        'sub_status', 'payload', 'payload_timestamp', 'step_entered_at',
        'received_at', 'matched_claim', 'note',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'payload_timestamp' => 'datetime',
            'step_entered_at' => 'datetime',
            'received_at' => 'datetime',
            'matched_claim' => 'boolean',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }
}
