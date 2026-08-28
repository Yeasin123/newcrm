<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached lead score used by Blast Hub prioritisation. Legacy `lead_ai_scores`
 * (unique per lead). Real model-driven scoring lands in Phase 11; until then
 * LeadBlaster::generateMissingScoresChunked seeds a status-based fallback.
 */
class LeadAiScore extends Model
{
    protected $table = 'lead_ai_scores';

    protected $fillable = ['lead_id', 'score', 'reason', 'scored_at'];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'scored_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadDetail::class, 'lead_id');
    }
}
