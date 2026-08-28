<?php

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model for the denormalised `analytics_customers` mirror table. Lives on the
 * `analytics` connection; written by App\Services\Analytics\AnalyticsSyncService.
 */
class AnalyticsCustomer extends Model
{
    protected $connection = 'analytics';

    protected $table = 'analytics_customers';

    protected $guarded = [];
}
