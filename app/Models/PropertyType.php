<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Legacy `property_types` catalog row. */
class PropertyType extends Model
{
    use SoftDeletes;

    protected $table = 'property_types';

    protected $fillable = ['title', 'yearly', 'monthly', 'currency', 'image'];
}
