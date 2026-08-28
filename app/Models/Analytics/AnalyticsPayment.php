<?php

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model for the denormalised `analytics_payments` mirror table. Lives on the
 * `analytics` connection; written by App\Services\Analytics\AnalyticsSyncService.
 */
class AnalyticsPayment extends Model
{
    protected $connection = 'analytics';

    protected $table = 'analytics_payments';

    protected $guarded = [];
}
