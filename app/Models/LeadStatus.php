<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Legacy `leads_status` table (note the plural-then-singular name). The set of
 * pipeline statuses a lead / converted lead can be in.
 */
class LeadStatus extends Model
{
    protected $table = 'leads_status';

    protected $fillable = ['title'];
}
