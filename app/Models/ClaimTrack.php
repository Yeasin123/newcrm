<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `claim_tracks` — the action/note timeline for a claim. */
class ClaimTrack extends Model
{
    protected $table = 'claim_tracks';

    protected $fillable = [
        'claim_id', 'user_id', 'create_date', 'duration', 'status',
        'button_text', 'comments', 'who_view',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
