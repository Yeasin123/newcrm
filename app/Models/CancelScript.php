<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Legacy `cancel_scripts` — retention scripts read to a customer requesting cancellation. */
class CancelScript extends Model
{
    protected $table = 'cancel_scripts';

    protected $fillable = ['title', 'script_text'];

    public function options(): HasMany
    {
        return $this->hasMany(CancelScriptOption::class, 'cancel_script_id');
    }
}
