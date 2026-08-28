<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lead selected into a Blast Hub run. Legacy `lead_blast_items`: unique per
 * (run, lead); `locked_until` holds the lead out of later runs for 28 days
 * once the CSV is downloaded.
 */
class LeadBlastItem extends Model
{
    protected $table = 'lead_blast_items';

    protected $fillable = [
        'lead_blast_run_id', 'lead_id', 'phone_normalized', 'ai_score',
        'locked_until', 'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_score' => 'integer',
            'locked_until' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LeadBlastRun::class, 'lead_blast_run_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadDetail::class, 'lead_id');
    }
}
