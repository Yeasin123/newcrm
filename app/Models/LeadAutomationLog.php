<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One automation attempt for a lead. Phase 11's brand-new table. The
 * (lead_id, action_key) unique key is what keeps every automation
 * duplicate-safe.
 */
class LeadAutomationLog extends Model
{
    protected $table = 'lead_automation_logs';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'lead_id', 'action_key', 'status', 'sent_at', 'error_message', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadDetail::class, 'lead_id');
    }
}
