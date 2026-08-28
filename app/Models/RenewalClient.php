<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `renewal_clients` — the renewal call queue (one row per client due to renew). */
class RenewalClient extends Model
{
    protected $table = 'renewal_clients';

    protected $fillable = ['client_id'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
