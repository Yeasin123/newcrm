<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `cancel_script_options` — the branch options within a cancel script. */
class CancelScriptOption extends Model
{
    protected $table = 'cancel_script_options';

    protected $fillable = ['cancel_script_id', 'title'];

    public function script(): BelongsTo
    {
        return $this->belongsTo(CancelScript::class, 'cancel_script_id');
    }
}
