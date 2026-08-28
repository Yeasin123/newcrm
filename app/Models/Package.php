<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A protection plan / coverage package. Legacy `packages`.
 * `yearly` / `monthly` prices are stored as varchar strings.
 */
class Package extends Model
{
    use SoftDeletes;

    protected $table = 'packages';

    protected $fillable = ['title', 'yearly', 'monthly', 'currency', 'detail'];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(
            PackageServicesList::class,
            'package_services',
            'package_id',
            'package_service_id',
        );
    }
}
