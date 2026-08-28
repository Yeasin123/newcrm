<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedPaymentCommunication extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function failedPaymentCase()
    {
        return $this->belongsTo(FailedPaymentCase::class);
    }

    public function getMaskedRecipientAttribute(): string
    {
        $recipient = trim((string) ($this->recipient ?? ''));

        if ($recipient === '') {
            return '';
        }

        if (str_contains($recipient, '@')) {
            [$local, $domain] = array_pad(explode('@', $recipient, 2), 2, '');

            if ($domain === '') {
                return $this->maskFallback($recipient);
            }

            $first = mb_substr($local, 0, 1);

            return $first.'***@'.$domain;
        }

        $digits = preg_replace('/\D+/', '', $recipient) ?? '';

        if ($digits !== '') {
            if (strlen($digits) <= 4) {
                return str_repeat('*', max(4, strlen($digits)));
            }

            return str_repeat('*', max(4, strlen($digits) - 4)).substr($digits, -4);
        }

        return $this->maskFallback($recipient);
    }

    public function getDeliveryLabelAttribute(): string
    {
        return in_array(strtolower((string) $this->status), ['simulated', 'blocked'], true) ? 'No Delivery' : 'Delivery Recorded';
    }

    protected function maskFallback(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 4) {
            return str_repeat('*', max(4, strlen($value)));
        }

        return mb_substr($value, 0, 1).str_repeat('*', max(3, strlen($value) - 4)).mb_substr($value, -3);
    }
}
