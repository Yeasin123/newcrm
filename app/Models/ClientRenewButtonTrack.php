<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `client_renew_button_tracks` — renewal viewboard button/note log. */
class ClientRenewButtonTrack extends Model
{
    protected $table = 'client_renew_button_tracks';

    protected $fillable = ['client_id', 'user_id', 'create_date', 'duration', 'status', 'button_text', 'comments'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
