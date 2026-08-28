<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status-change timeline for a converted lead (feeds the Board History module
 * in Phase 4). Legacy `converted_lead_statuses` — keep the misspelled
 * `onverted_lead_id` column alongside `converted_lead_id`.
 */
class ConvertedLeadStatus extends Model
{
    protected $table = 'converted_lead_statuses';

    protected $fillable = [
        'campaign_id', 'converted_lead_id', 'onverted_lead_id', 'lead_id',
        'status', 'notes',
    ];

    public function convertedLead(): BelongsTo
    {
        return $this->belongsTo(ConvertedLead::class, 'converted_lead_id');
    }

    public function leadStatus(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'status');
    }
}
