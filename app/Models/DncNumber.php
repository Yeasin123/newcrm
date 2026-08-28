<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A do-not-call number. Legacy `dnc_numbers` — `phone_normalized` is the
 * 10-digit form (leading US 1 stripped), unique.
 */
class DncNumber extends Model
{
    protected $table = 'dnc_numbers';

    protected $fillable = ['phone_normalized', 'source', 'notes'];
}
