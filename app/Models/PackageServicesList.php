<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Legacy `package_services_list` — the catalog of service line items. */
class PackageServicesList extends Model
{
    use SoftDeletes;

    protected $table = 'package_services_list';

    protected $fillable = ['title'];
}
