<?php

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model for the denormalised `analytics_sales_events` mirror table. Lives on the
 * `analytics` connection; written by App\Services\Analytics\AnalyticsSyncService.
 */
class AnalyticsSalesEvent extends Model
{
    protected $connection = 'analytics';

    protected $table = 'analytics_sales_events';

    protected $guarded = [];
}
