<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Legacy `property_sizes` catalog row. */
class PropertySize extends Model
{
    use SoftDeletes;

    protected $table = 'property_sizes';

    protected $fillable = ['title', 'yearly', 'monthly', 'currency'];
}
