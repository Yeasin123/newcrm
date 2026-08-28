<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedPaymentCommunicationTemplate extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'channels' => 'array',
        'active' => 'bool',
    ];
}
