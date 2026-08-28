<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A service-call / warranty claim. Legacy `claims`.
 *
 *   status: 0 = open, 1 = paid, 2 = closed, 3 = bypassed  (see ClaimStatus enum)
 *   `system` / `brand` / `issue` are FK-ish columns into the catalog tables.
 */
class Claim extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'claims';

    protected $fillable = [
        'client_id', 'claim_no', 'system', 'issue', 'other_issue', 'brand',
        'serial_no', 'location', 'model_no', 'last_time_working_detail',
        'problem', 'status', 'datetime', 'claim_notes', 'claim_color',
        'serial_number', 'claim_file', 'tech_id', 'tech_schedule',
        'tech_diagnosis', 'claim_status', 'owed_price', 'total_price',
        'tech_paid_date', 'tech_paid_status', 'who_view', 'is_callfee',
        'refund_date', 'payment_fail', 'authorize_status', 'payment_faild_date',
        'replit_claim_payload', 'replit_event', 'replit_workflow_step',
        'replit_status', 'replit_sub_status', 'replit_payload_received_at',
    ];

    protected function casts(): array
    {
        return [
            'replit_claim_payload' => 'array',
            'replit_payload_received_at' => 'datetime',
            'total_price' => 'float',
            'owed_price' => 'float',
            'is_callfee' => 'boolean',
            'tech_paid_status' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'tech_id');
    }

    public function systemRef(): BelongsTo
    {
        return $this->belongsTo(System::class, 'system');
    }

    public function brandRef(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand');
    }

    public function issueRef(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'issue');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'claim_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ClaimFile::class, 'claim_id');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(ClaimTrack::class, 'claim_id')->latest('id');
    }

    public function closedDetails(): HasMany
    {
        return $this->hasMany(ClaimClosedDetail::class, 'claim_id')->latest('id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(TechRating::class, 'claim_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('claim_no', 'like', "%{$term}%")
                ->orWhere('serial_no', 'like', "%{$term}%")
                ->orWhere('model_no', 'like', "%{$term}%")
                ->orWhereHas('client', fn (Builder $c) => $c->search($term));
        });
    }
}
