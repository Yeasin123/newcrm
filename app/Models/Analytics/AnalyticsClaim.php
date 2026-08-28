<?php

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model for the denormalised `analytics_claims` mirror table. Lives on the
 * `analytics` connection; written by App\Services\Analytics\AnalyticsSyncService.
 */
class AnalyticsClaim extends Model
{
    protected $connection = 'analytics';

    protected $table = 'analytics_claims';

    protected $guarded = [];
}
