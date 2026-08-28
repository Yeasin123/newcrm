<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `email_signatures` — keep the misspelled `eamil_text` column. */
class EmailSignature extends Model
{
    protected $table = 'email_signatures';

    protected $fillable = ['name', 'subject', 'eamil_text', 'user_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
