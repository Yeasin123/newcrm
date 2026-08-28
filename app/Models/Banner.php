<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Legacy `banners` table. */
class Banner extends Model
{
    protected $table = 'banners';

    protected $guarded = ['id'];
}
