<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-lead salesman action log. Legacy `track_salesman_actions` — note the
 * uppercase `STORE_DATE` column and the string action columns (each holds a
 * timestamp string when that action was taken).
 */
class TrackSalesmanAction extends Model
{
    protected $table = 'track_salesman_actions';

    protected $fillable = [
        'lead_id', 'user_id', 'click_to_call', 'save', 'send_sms', 'send_mail',
        'voice', 'convert_to_client', 'lead_status', 'STORE_DATE',
    ];

    protected function casts(): array
    {
        return [
            'STORE_DATE' => 'datetime',
        ];
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadDetail::class, 'lead_id');
    }

    /** The action columns that carry a "was done" timestamp string. */
    public const ACTIONS = ['click_to_call', 'save', 'send_sms', 'send_mail', 'voice', 'convert_to_client'];
}
