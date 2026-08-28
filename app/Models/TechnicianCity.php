<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `technicians_cities`. */
class TechnicianCity extends Model
{
    protected $table = 'technicians_cities';

    protected $fillable = ['name', 'state_id'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(TechnicianState::class, 'state_id');
    }
}
