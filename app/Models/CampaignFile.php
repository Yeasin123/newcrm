<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A lead campaign / import batch. Legacy `campaign_files`.
 * status: 0 = closed, 1 = active, 2 = pending
 */
class CampaignFile extends Model
{
    use SoftDeletes;

    protected $table = 'campaign_files';

    protected $fillable = [
        'user_id', 'name', 'status', 'camp_price', 'camp_price_two',
        'price_type', 'camp_per_lead', 'camp_per_camp',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(LeadDetail::class, 'campaign_id');
    }
}
