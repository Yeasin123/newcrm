<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Legacy `contact_us` table. */
class ContactUs extends Model
{
    protected $table = 'contact_us';

    protected $guarded = ['id'];
}
