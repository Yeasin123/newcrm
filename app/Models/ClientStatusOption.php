<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Legacy `client_statuses` — the rep-selectable status labels on the
 * Payment Failed viewboard. Named "…Option" to avoid clashing with the
 * `App\Enums\ClientStatus` int enum (clients.status).
 */
class ClientStatusOption extends Model
{
    protected $table = 'client_statuses';

    protected $fillable = ['title'];
}
