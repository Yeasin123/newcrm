<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A stored "affirmative reply" phrase. Legacy `sms_texts` table (a handful of
 * rows). When an inbound entrance SMS matches one of these words, the lead is
 * promoted to the top of the call queue.
 */
class SmsText extends Model
{
    protected $table = 'sms_texts';

    protected $fillable = ['sms_word'];
}
