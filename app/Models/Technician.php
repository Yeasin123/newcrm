<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A field technician. Legacy `technicians` — note `city` and `state` are JSON
 * columns (arrays of coverage areas); `services` is a JSON string too.
 * `in_ex`: 0 = internal, 1 = external.
 */
class Technician extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'technicians';

    protected $fillable = [
        'first_name', 'last_name', 'business_name', 'contact', 'email',
        'address', 'longitude', 'latitude', 'city', 'state', 'zip_code',
        'services', 'notes', 'call_fee', 'in_ex',
    ];

    protected function casts(): array
    {
        return [
            'city' => 'array',
            'state' => 'array',
            'services' => 'array',
            'call_fee' => 'float',
            'in_ex' => 'boolean',
        ];
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'tech_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(TechRating::class, 'tech_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: (string) $this->business_name;
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
                ->orWhere('business_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('contact', 'like', "%{$term}%");
        });
    }
}
