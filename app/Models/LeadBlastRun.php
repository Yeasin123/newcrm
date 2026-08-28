<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Blast Hub generation. Legacy `lead_blast_runs`: the filter set an admin
 * chose, the eligibility maths (total / selected / excluded-*), the CSV path,
 * and a status of generated → downloaded.
 */
class LeadBlastRun extends Model
{
    protected $table = 'lead_blast_runs';

    protected $fillable = [
        'user_id', 'blast_type', 'carrier_filter', 'campaign_id', 'requested_count',
        'min_ai_score', 'dnc_excluded', 'total_eligible', 'selected_count',
        'excluded_locked_count', 'excluded_dnc_count', 'excluded_non_cell_count',
        'csv_path', 'status',
    ];

    protected function casts(): array
    {
        return [
            'dnc_excluded' => 'boolean',
            'min_ai_score' => 'integer',
            'total_eligible' => 'integer',
            'selected_count' => 'integer',
            'excluded_locked_count' => 'integer',
            'excluded_dnc_count' => 'integer',
            'excluded_non_cell_count' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(LeadBlastItem::class, 'lead_blast_run_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CampaignFile::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
