<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Legacy `brands` — appliance manufacturer catalog. */
class Brand extends Model
{
    use SoftDeletes;

    protected $table = 'brands';

    protected $fillable = ['title', 'testdata'];
}
