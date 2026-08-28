<?php

/*
|--------------------------------------------------------------------------
| Inbound campaign lead-intake links
|--------------------------------------------------------------------------
| Public, API-key-style endpoints that partner campaigns POST new leads to.
| The legacy app had one copy-paste controller method per link; here a single
| App\Services\Lead\CampaignLeadIntakeService is driven by this map. Paths and
| the plain-text responses ('ok' / 'empty_data') stay byte-identical.
|
|   campaign_id  - lead_details.campaign_id stamped on the row
|   phone_key    - request field holding the phone number
|   mode         - 'upsert' (updateOrCreate on contact+campaign_id)
|                  'create' (always a fresh row, no dedup)
|   inquiry_tag  - set lead_tag = 'Inquiry'
|   modernize    - apply the Modernize-only extras (progress_bar, lead_type MD…)
*/

return [
    'channels' => [
        'ahc' => ['campaign_id' => 157, 'phone_key' => 'phone_number', 'mode' => 'upsert', 'inquiry_tag' => true],
        'ahw' => ['campaign_id' => 158, 'phone_key' => 'phone_number', 'mode' => 'upsert', 'inquiry_tag' => true],
        'lcl' => ['campaign_id' => 159, 'phone_key' => 'phone_number', 'mode' => 'upsert', 'inquiry_tag' => false],
        'gbl' => ['campaign_id' => 160, 'phone_key' => 'phone_number', 'mode' => 'upsert', 'inquiry_tag' => false],
        'jltwo' => ['campaign_id' => 161, 'phone_key' => 'phone_number', 'mode' => 'upsert', 'inquiry_tag' => false],

        'modanize' => ['campaign_id' => 139, 'phone_key' => 'contact', 'mode' => 'create', 'inquiry_tag' => false, 'modernize' => true],
    ],
];
