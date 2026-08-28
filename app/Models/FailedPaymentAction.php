<?php

namespace App\Models;

use App\Enums\FailedPaymentActionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedPaymentAction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'performed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => FailedPaymentActionStatus::RECORDED,
    ];

    public function failedPaymentCase()
    {
        return $this->belongsTo(FailedPaymentCase::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
