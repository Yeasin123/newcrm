<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Legacy `addons` catalog row. */
class Addon extends Model
{
    use SoftDeletes;

    protected $table = 'addons';

    protected $fillable = ['title', 'subtitle', 'yearly', 'monthly', 'currency'];
}
