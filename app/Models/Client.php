<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A policyholder. Maps to the legacy `clients` table (shared with the old
 * app and the customer portal, which authenticates on the `user` guard).
 * Do not add or rename columns.
 *
 * Status conventions carried over verbatim:
 *   - status:            1 = active / good standing, 0 = suspended
 *   - login_off:         1 = portal login blocked ("policy canceled")
 *   - payment_structure: '1' = monthly, '2' = yearly (+ optional installment)
 *   - star:              1 = flagged/starred by staff
 *   - bypass_payment:    1 = billing bypassed
 */
class Client extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $table = 'clients';

    protected $guard = 'user';

    /**
     * Human / staff editable columns only. Gateway + document + token columns
     * (stripe_*, authorize_*, pass_token, signature, *_pdf, transaction ids)
     * are deliberately excluded from mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'policy_number', 'first_name', 'last_name', 'email', 'password',
        'contact', 'address', 'billing_address', 'longitude', 'latitude',
        'city', 'state', 'zip_code', 'picture',
        'payment_structure', 'payment_type', 'installment', 'paid_installment',
        'property_size', 'property_type', 'package', 'services',
        'discount', 'bypass_payment', 'call_fee', 'salesman_name', 'description',
        'start_date', 'end_date', 'custom_s_date', 'custom_e_date',
        'custom_purchaes_date', 'custom_price',
        'status', 'login_off', 'star', 'disputed', 'client_type', 'promo_code',
        'price_cal_response', 'multi_property', 'is_renew', 'renewed', 'plan_update',
        'agent_id', 'authorize_account_id', 'authorize_status',
        'cancle_date', 'refund_date',
    ];

    protected $hidden = [
        'password', 'pass_token', 'card_number', 'bank_info',
    ];

    protected function casts(): array
    {
        return [
            'login_off' => 'boolean',
            'star' => 'boolean',
            'renewed' => 'boolean',
            'plan_update' => 'boolean',
            'trial_ends_at' => 'datetime',
            'payment_faild_date' => 'datetime',
            'plan_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'overpaid_flag_amount' => 'decimal:2',
            'overpaid_flagged_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type');
    }

    public function propertySize(): BelongsTo
    {
        return $this->belongsTo(PropertySize::class, 'property_size');
    }

    public function propertyPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'client_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'client_id')->latest('id');
    }

    public function cardProfiles(): HasMany
    {
        return $this->hasMany(CardProfile::class, 'client_id');
    }

    public function authorizeAccount(): BelongsTo
    {
        return $this->belongsTo(AuthorizeAccount::class, 'authorize_account_id');
    }

    public function paymentHistories(): HasMany
    {
        return $this->hasMany(PaymentHistory::class, 'client_id');
    }

    public function cardProfilesRel(): HasMany
    {
        return $this->hasMany(CardProfile::class, 'client_id');
    }

    public function renewalEntry(): HasOne
    {
        return $this->hasOne(RenewalClient::class, 'client_id');
    }

    public function renewAgreementPdfs(): HasMany
    {
        return $this->hasMany(RenewAgrementPdf::class, 'client_id')->latest('id');
    }

    public function buttonTracks(): HasMany
    {
        return $this->hasMany(ClientButtonTrack::class, 'client_id')->latest('id');
    }

    public function renewButtonTracks(): HasMany
    {
        return $this->hasMany(ClientRenewButtonTrack::class, 'client_id')->latest('id');
    }

    public function latestPaymentHistory(): HasOne
    {
        return $this->hasOne(PaymentHistory::class, 'client_id')->latestOfMany();
    }

    /**
     * Most recent policy-billing payment (excludes claim payments and manual
     * one-off charges). Ported verbatim from the legacy Client model.
     */
    public function latestEligiblePolicyPaymentHistory(): HasOne
    {
        return $this->hasOne(PaymentHistory::class, 'client_id')
            ->where('claim_payment', 0)
            ->where('is_custom', 0)
            ->where(fn (Builder $q) => $q->whereNull('claim_id')->orWhere('claim_id', 0))
            ->latestOfMany();
    }

    public function latestFailedPolicyPaymentHistory(): HasOne
    {
        return $this->hasOne(PaymentHistory::class, 'client_id')
            ->where('payment_status', 0)
            ->where('claim_payment', 0)
            ->where('is_custom', 0)
            ->latestOfMany('created_at');
    }

    public function latestPaidPolicyPaymentHistory(): HasOne
    {
        return $this->hasOne(PaymentHistory::class, 'client_id')
            ->where('payment_status', 1)
            ->where('claim_payment', 0)
            ->where('is_custom', 0)
            ->latestOfMany('created_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function isMonthly(): bool
    {
        return (string) $this->payment_structure === '1';
    }

    public function isYearly(): bool
    {
        return (string) $this->payment_structure === '2';
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 0);
    }

    public function scopeStarred(Builder $query): Builder
    {
        return $query->where('star', 1);
    }

    /** Preset segment views used by the Customers module. */
    public const SEGMENTS = [
        'all' => 'All customers',
        'active' => 'Active',
        'suspended' => 'Suspended / failed',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'starred' => 'Starred',
        'disputed' => 'Disputed',
        'bypass' => 'Bypass payment',
        'canceled' => 'Canceled (login off)',
        'unsuccessful' => 'Failed last payment',
        'renewal-today' => 'Renewing today',
    ];

    public function scopeSegment(Builder $query, string $segment): Builder
    {
        return match ($segment) {
            'active' => $query->where('status', 1),
            'suspended' => $query->where('status', 0),
            'monthly' => $query->where('payment_structure', '1'),
            'yearly' => $query->where('payment_structure', '2'),
            'starred' => $query->where('star', 1),
            'disputed' => $query->whereNotNull('disputed')->where('disputed', '!=', ''),
            'bypass' => $query->where('bypass_payment', 1),
            'canceled' => $query->where('login_off', 1),
            'unsuccessful' => $query->whereHas('latestFailedPolicyPaymentHistory'),
            'renewal-today' => $query->whereDate('end_date', today()),
            default => $query,
        };
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
