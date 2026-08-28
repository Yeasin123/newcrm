<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single inbound email-engagement event (quote requested, email opened /
 * clicked, CEL) for a lead. Legacy `constant_contacts` table — one row per
 * webhook hit, keyed to `lead_details` by `lead_id`.
 */
class ConstantContact extends Model
{
    protected $table = 'constant_contacts';

    protected $fillable = [
        'lead_id', 'email_text', 'campaign', 'email_time', 'webhook_name',
    ];
}
