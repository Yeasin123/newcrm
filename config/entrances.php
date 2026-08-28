<?php

/*
|--------------------------------------------------------------------------
| Inbound "entrance" SMS-reply webhooks
|--------------------------------------------------------------------------
| Each key below is one public webhook endpoint that an external SMS
| campaign platform POSTs inbound replies to. The legacy app had one
| near-identical copy-paste controller per endpoint; here a single
| App\Services\Lead\EntranceReplyService is driven by this map. Route paths
| and JSON response shapes stay byte-identical to new-ahc.
|
| Per channel:
|   account_key   - stamped onto the entrances row (external channel id)
|   webhook_name  - entrances.webhook_name marker ('En' for the numbered set)
|   flag_column   - webhooks.<COL> day-flag written on an affirmative match
|   strip         - phone normalisation: 'first_char' | 'leading_one' | 'us_digits'
|   match         - sms_texts lookup: 'like' | 'normalized'
|   campaign_fields - copy campaign_id/channel_id/workspace_id from the payload
|   round_robin   - push the reply on to the VanillaSoft round-robin
|   response      - 'plain' {status} | 'trace' {status,trace_id} | 'yair'
*/

$numbered = [
    'account_key' => null, // set per entry below
    'webhook_name' => 'En',
    'flag_column' => 'EN',
    'strip' => 'first_char',
    'match' => 'like',
    'campaign_fields' => true,
    'round_robin' => true,
    'response' => 'plain',
];

// Exact `entrances.account_key` string each legacy controller saved.
$numberedKeys = [
    'zero' => 'EGKORLQELI8XN2375',
    'first' => 'ENTRANCE8HEVSK9WKA4055',
    'second' => 'ENTRANCEMSBUHZ3PK84479',
    'third' => 'EGKU6FXP8P1XP4480',
    'fourth' => 'EGKCDMRL16JL44596',
    'fifth' => 'EGK4AI8QNS0E84665',
    'sixth' => 'EGKQ0TGHATGLZ4681',
    'seventh' => 'ENTRANCEXFS30B58WB5099',
    'eigth' => 'ENTRANCEO9IF7WYVMF5100',
    'nineth' => 'ENTRANCEGJYVUEA7MY5101',
];

$channels = [];
foreach ($numberedKeys as $slug => $key) {
    $channels[$slug] = array_merge($numbered, ['account_key' => $key]);
}

$channels['mm'] = [
    'account_key' => 'D1SDuLTV8xZHyf1jWdET',
    'webhook_name' => 'MM',
    'flag_column' => 'MM',
    'strip' => 'leading_one',
    'match' => 'like',
    'campaign_fields' => false,
    'round_robin' => false,
    'response' => 'trace',
];

$channels['avchad'] = [
    'account_key' => 'TMZY83IoNMLUTq40D6sS',
    'webhook_name' => 'AVCHAD',
    'flag_column' => 'AVCHAD',
    'strip' => 'leading_one',
    'match' => 'like',
    'campaign_fields' => false,
    'round_robin' => false,
    'response' => 'trace',
];

// `/second/new/webhook` — an SMS reply delivered as {data:{from_address,
// message_body, message}}. Same entrance flow, flagged LG.
$channels['lg'] = [
    'account_key' => null,
    'webhook_name' => 'LG',
    'flag_column' => 'LG',
    'strip' => 'drop_two',
    'match' => 'like',
    'number_key' => 'from_address',
    'match_key' => 'message',
    'store_key' => 'message_body',
    'campaign_fields' => false,
    'round_robin' => true,
    'response' => 'plain',
];

// `/clicksend/get/notification` — a ClickSend inbound SMS. Same entrance flow,
// flagged CS; the message id from the ClickSend payload is kept.
$channels['cs'] = [
    'account_key' => null,
    'webhook_name' => 'Cs',
    'flag_column' => 'CS',
    'strip' => 'drop_two',
    'match' => 'like',
    'number_key' => 'sms',
    'message_id_key' => 'message_id',
    'campaign_fields' => false,
    'round_robin' => false,
    'response' => 'plain',
];

$channels['yair'] = [
    'account_key' => 'Y6TtQlYURjTPFyBn4bQe',
    'webhook_name' => 'YAIR',
    'flag_column' => 'YAIR',
    'strip' => 'us_digits',
    'match' => 'normalized',
    'campaign_fields' => false,
    'round_robin' => false,
    'response' => 'yair',
];

return [

    // Master switch for the outbound VanillaSoft round-robin push. OFF by
    // default so no external HTTP fires in dev / tests. The inbound handlers
    // always run regardless.
    'outbound_enabled' => (bool) env('ENTRANCE_OUTBOUND_ENABLED', false),

    // Round-robin target URLs (VanillaSoft post endpoints), keyed 1..4.
    'round_robin_urls' => [
        1 => env('ENTRANCE_RR_URL_1', 'https://s2.vanillasoft.net/web/post.aspx?id=1003818'),
        2 => env('ENTRANCE_RR_URL_2', 'https://s2.vanillasoft.net/web/post.aspx?id=1004317'),
        3 => env('ENTRANCE_RR_URL_3', 'https://s2.vanillasoft.net/web/post.aspx?id=1004318'),
        4 => env('ENTRANCE_RR_URL_4', 'https://s2.vanillasoft.net/web/post.aspx?id=1004319'),
    ],

    'channels' => $channels,
];
