<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Legacy `technicians_states`. */
class TechnicianState extends Model
{
    protected $table = 'technicians_states';

    protected $fillable = ['name'];

    public function cities(): HasMany
    {
        return $this->hasMany(TechnicianCity::class, 'state_id');
    }
}
