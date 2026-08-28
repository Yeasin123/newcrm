<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Legacy `technicians_services`. */
class TechnicianService extends Model
{
    protected $table = 'technicians_services';

    protected $fillable = ['name'];
}
