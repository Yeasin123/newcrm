<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedPaymentSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'setting_value' => 'array',
        'is_active' => 'boolean',
    ];
}
