<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `tech_ratings` — a customer's star rating of a technician on a claim. */
class TechRating extends Model
{
    protected $table = 'tech_ratings';

    protected $fillable = ['client_id', 'claim_id', 'tech_id', 'stars', 'notes'];

    protected function casts(): array
    {
        return [
            'stars' => 'integer',
        ];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'tech_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }
}
