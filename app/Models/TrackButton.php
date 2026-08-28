<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A dialer disposition roll-up for a lead. Legacy `track_buttons` table: one
 * row accumulates a JSON `button_text` map ({"Sale":1,"No Answer":3}), merged
 * `comments`, and total `duration` (seconds) across a rep's calls, until a
 * `status` is stamped — then the next disposition starts a fresh row.
 */
class TrackButton extends Model
{
    protected $table = 'track_buttons';

    protected $fillable = [
        'lead_id', 'user_id', 'create_date', 'duration', 'status',
        'button_text', 'comments',
    ];
}
