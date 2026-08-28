<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `offer_notes` — a retention offer a rep made to a client on the viewboard. */
class OfferNote extends Model
{
    protected $table = 'offer_notes';

    protected $fillable = ['user_id', 'client_id', 'option_title', 'notes', 'outcome', 'start_date', 'end_date'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
