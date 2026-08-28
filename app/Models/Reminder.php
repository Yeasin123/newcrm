<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A follow-up reminder against a lead. Legacy `reminders` (`date` and `time`
 * are varchar strings, `user_id` is varchar).
 */
class Reminder extends Model
{
    use SoftDeletes;

    protected $table = 'reminders';

    protected $fillable = ['user_id', 'lead_id', 'date', 'time', 'description'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadDetail::class, 'lead_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
