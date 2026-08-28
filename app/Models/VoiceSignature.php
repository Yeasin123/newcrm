<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `voice_signatures` (`audio_file` is a stored path). */
class VoiceSignature extends Model
{
    protected $table = 'voice_signatures';

    protected $fillable = ['name', 'audio_file', 'user_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
