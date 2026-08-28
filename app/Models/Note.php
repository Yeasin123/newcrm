<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps to the legacy `notes` table. Note the loose column types:
 * `client_id` and `user_id` are varchar, `date_time` is a varchar string.
 */
class Note extends Model
{
    protected $table = 'notes';

    protected $fillable = [
        'lead_id', 'user_id', 'client_id', 'notes', 'date_time', 'status',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
