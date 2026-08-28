<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Legacy `systems` — covered appliance/system catalog. */
class System extends Model
{
    use SoftDeletes;

    protected $table = 'systems';

    protected $fillable = ['title', 'brand', 'brand_title'];

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class, 'system_id');
    }
}
