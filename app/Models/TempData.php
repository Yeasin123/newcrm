<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hand-off row between the marketing landing form and the checkout funnel
 * when the visitor arrives without a session (legacy newWlUserinfo /
 * newMainWlUserinfo). Legacy `temp_data`.
 */
class TempData extends Model
{
    protected $table = 'temp_data';

    protected $fillable = [
        'first_name', 'last_name', 'email', 'contact', 'zip_code',
        'campaign_id', 'serial_number',
    ];
}
