<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Legacy `client_renew_statuses` — rep-selectable status labels on the Renewal viewboard. */
class ClientRenewStatus extends Model
{
    protected $table = 'client_renew_statuses';

    protected $fillable = ['title'];
}
