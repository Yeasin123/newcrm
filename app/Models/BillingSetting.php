<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Key/value row backing the admin billing switch. New table `billing_settings`. */
class BillingSetting extends Model
{
    protected $table = 'billing_settings';

    protected $fillable = ['key', 'value'];
}
