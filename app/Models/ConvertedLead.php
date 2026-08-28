<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A lead that made it onto the Sale Sheet. Legacy `converted_leads` — a
 * snapshot copied from `lead_details` at conversion time. `lead_id` points
 * back at the originating `lead_details.id`; `status` → `leads_status.id`.
 */
class ConvertedLead extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'converted_leads';

    protected $fillable = [
        'campaign_id', 'user_id', 'lead_id', 'policy_number', 'first_name',
        'last_name', 'contact', 'email', 'address', 'city', 'state', 'zip_code',
        'notes', 'status', 'reminder_id', 'reminder_status', 'property_size',
        'property_type', 'package', 'services_call_fee', 'live_charges',
        'additional_comments', 'apply_discounts', 'payment_terms', 'instalments',
        'sale_man', 'longitude', 'latitude', 'email_date_time', 'mail_type',
        'show_status', 'color_change', 'contact_text', 'phone_company',
        'message', 'priority_indx',
    ];

    protected function casts(): array
    {
        return [
            'show_status' => 'boolean',
            'color_change' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadDetail::class, 'lead_id');
    }

    public function leadStatus(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'status');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CampaignFile::class, 'campaign_id');
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sale_man');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ConvertedLeadStatus::class, 'converted_lead_id')->latest('id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('contact', 'like', "%{$term}%")
                ->orWhere('policy_number', 'like', "%{$term}%");
        });
    }
}
