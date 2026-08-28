<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A "check your zip" capture from the plan-shop landing form. Legacy
 * `zip_codes` (written by Web\ShopPlansController::zipcode).
 */
class ZipCode extends Model
{
    protected $table = 'zip_codes';

    protected $fillable = [
        'first_name', 'last_name', 'email', 'contact', 'address',
        'state', 'city', 'zip_code', 'status',
    ];
}
