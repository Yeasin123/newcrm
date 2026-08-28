<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Legacy `partners` table. */
class Partner extends Model
{
    protected $table = 'partners';

    protected $guarded = ['id'];
}
