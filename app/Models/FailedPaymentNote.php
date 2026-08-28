<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedPaymentNote extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function failedPaymentCase()
    {
        return $this->belongsTo(FailedPaymentCase::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }
}
