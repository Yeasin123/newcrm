<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One SMS in an admin↔salesman thread. Legacy `salesman_messages`:
 * `direction` outbound|inbound, `status` pending|sent|failed|received,
 * `read_at` set when the admin opens the thread. Threads are keyed by
 * (admin_id, salesman_id) — both point at `users`.
 */
class SalesmanMessage extends Model
{
    protected $table = 'salesman_messages';

    protected $fillable = [
        'admin_id', 'salesman_id', 'direction', 'status', 'from_number',
        'to_number', 'body', 'telnyx_message_id', 'telnyx_payload',
        'error_message', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'telnyx_payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }
}
