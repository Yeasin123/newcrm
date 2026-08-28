<?php

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model for the denormalised `analytics_leads` mirror table. Lives on the
 * `analytics` connection; written by App\Services\Analytics\AnalyticsSyncService.
 */
class AnalyticsLead extends Model
{
    protected $connection = 'analytics';

    protected $table = 'analytics_leads';

    protected $guarded = [];
}
