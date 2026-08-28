<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A purchased outbound caller-ID number. Legacy `buy_numbers` table — the pool
 * the Vanilasoft/3CX dialer rotates through, picking one whose area code (or
 * state) matches the lead and holding it via `lock_number = 'Lock'`.
 */
class BuyNumber extends Model
{
    protected $table = 'buy_numbers';

    protected $fillable = [
        'contact_no', 'user_id', 'call_time', 'state', 'city', 'lock_number',
    ];

    protected function casts(): array
    {
        return ['call_time' => 'datetime'];
    }
}
