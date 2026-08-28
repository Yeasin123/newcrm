<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `client_renew_track_actions` — renewal viewboard rep action log. */
class ClientRenewTrackAction extends Model
{
    protected $table = 'client_renew_track_actions';

    protected $fillable = [
        'client_id', 'user_id', 'click_to_call', 'save', 'send_sms', 'send_mail',
        'voice', 'convert_to_client', 'client_status', 'STORE_DATE',
    ];

    protected function casts(): array
    {
        return ['STORE_DATE' => 'datetime'];
    }

    public const ACTIONS = ['click_to_call', 'save', 'send_sms', 'send_mail', 'voice', 'convert_to_client'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
