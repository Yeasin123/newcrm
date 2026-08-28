<?php

namespace App\Models;

use App\Observers\LeadDetailObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An inbound lead. Legacy `lead_details` (~6M rows — always paginate, never
 * load unbounded). `status` points at `leads_status.id`; `user_id` (varchar)
 * is the assigned rep, `sale_man` (bigint) the attributed salesman;
 * `campaign_id` → `campaign_files.id`.
 */
#[ObservedBy(LeadDetailObserver::class)]
class LeadDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lead_details';

    protected $fillable = [
        'campaign_id', 'user_id', 'policy_number', 'first_name', 'last_name',
        'contact', 'email', 'address', 'city', 'state', 'zip_code', 'notes',
        'status', 'reminder_id', 'reminder_status', 'property_size',
        'property_type', 'package', 'services_call_fee', 'live_charges',
        'additional_comments', 'apply_discounts', 'payment_terms', 'instalments',
        'sale_man', 'longitude', 'latitude', 'email_date_time', 'mail_type',
        'color_change', 'contact_text', 'phone_company', 'message',
        'priority_indx', 'show_status', 'lead_type', 'lead_tag', 'lock_time',
        'lock_lead', 'addons', 'is_download',
        // Inbound SMS-reply (entrance) fields.
        'sms_mail', 'not_pull', 'serial_number', 'msg_date', 'priority_checker',
        // Inbound campaign-intake / email-engagement fields.
        'progress_bar', 'modernize_id', 'mail',
    ];

    protected function casts(): array
    {
        return [
            'color_change' => 'boolean',
            'show_status' => 'boolean',
            'is_download' => 'boolean',
            'msg_date' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CampaignFile::class, 'campaign_id');
    }

    public function leadStatus(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'status');
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class, 'reminder_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'lead_id')->latest('id');
    }

    /**
     * Note the name: `lead_details` has its own `notes` text column, so the
     * note relation cannot be called `notes()` (it would be shadowed).
     */
    public function leadNotes(): HasMany
    {
        return $this->hasMany(Note::class, 'lead_id')->latest('id');
    }

    /** Assigned rep (`user_id` is stored as a varchar). */
    public function assignedRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Attributed salesman. */
    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sale_man');
    }

    public function convertedLead(): BelongsTo
    {
        return $this->belongsTo(ConvertedLead::class, 'id', 'lead_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

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
                ->orWhere('contact', 'like', "%{$term}%");
        });
    }

    public function scopeAssignedTo(Builder $query, string|int|null $userId): Builder
    {
        return $userId === null || $userId === ''
            ? $query
            : $query->where('user_id', (string) $userId);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', ''));
    }
}
