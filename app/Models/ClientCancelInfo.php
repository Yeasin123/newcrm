<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `client_cancel_infos` — which cancel script/option a rep last used with a client. */
class ClientCancelInfo extends Model
{
    protected $table = 'client_cancel_infos';

    protected $fillable = ['client_id', 'script_id', 'cancel_script_option_id', 'rep_notes', 'last_selected_at'];

    protected function casts(): array
    {
        return ['last_selected_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
