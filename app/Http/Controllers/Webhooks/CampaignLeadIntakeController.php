<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\Lead\CampaignLeadIntakeService;
use Illuminate\Http\Request;

/**
 * Single entry point for the partner campaign lead-intake links. The channel
 * slug is a route default selecting a config('campaign_intake.channels.*')
 * row. Responds with the legacy plain-text body: 'ok' or 'empty_data'.
 */
class CampaignLeadIntakeController
{
    public function __construct(private readonly CampaignLeadIntakeService $service) {}

    public function __invoke(Request $request, string $channel): string
    {
        $config = config("campaign_intake.channels.$channel");

        abort_if(! is_array($config), 404);

        // Legacy had no error handling here — a failure surfaces as a 500.
        return $this->service->store($request->all(), $config);
    }
}
