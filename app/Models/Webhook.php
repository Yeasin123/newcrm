<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-lead, per-day audit flags for inbound webhook activity. Legacy
 * `webhooks` table: one column per lead source (`EN`, `MM`, `YAIR`, `AVCHAD`,
 * `CEL`, `QUOTE`, `WL`, `CS`, …). A row is written at most once per lead per
 * `STORE_DATE` day per source, each flag column just holding its own name as
 * a marker string. Column names (and the uppercase `STORE_DATE`) are
 * load-bearing.
 */
class Webhook extends Model
{
    protected $table = 'webhooks';

    public const SOURCE_COLUMNS = [
        'CEL', 'Klay', 'WL', 'EN', 'MM', 'QUOTE', 'INST', 'LG', 'CS', 'BHC',
        'YAIR', 'AVCHAD', 'MESSAGE',
    ];

    protected $fillable = [
        'lead_id', 'STORE_DATE',
        'CEL', 'Klay', 'WL', 'EN', 'MM', 'QUOTE', 'INST', 'LG', 'CS', 'BHC',
        'YAIR', 'AVCHAD', 'MESSAGE',
    ];

    public function leadDetail(): BelongsTo
    {
        return $this->belongsTo(LeadDetail::class, 'lead_id');
    }

    protected function casts(): array
    {
        return [
            'STORE_DATE' => 'datetime',
        ];
    }
}
