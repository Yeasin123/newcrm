<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `sms_signatures`. */
class SmsSignature extends Model
{
    protected $table = 'sms_signatures';

    protected $fillable = ['name', 'message_text', 'user_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
