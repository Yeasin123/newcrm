<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedPaymentAuditLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'safe_request_metadata' => 'array',
        'metadata' => 'array',
    ];

    public function failedPaymentCase()
    {
        return $this->belongsTo(FailedPaymentCase::class);
    }

    public function adminUser()
    {
        return $this->belongsTo(User::class, 'admin_id')->withDefault();
    }
}
