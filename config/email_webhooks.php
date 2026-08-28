<?php

/*
|--------------------------------------------------------------------------
| Inbound email-engagement lead webhooks (quote / pquote / CEL family)
|--------------------------------------------------------------------------
| Each key is a public endpoint an email platform POSTs to when a lead
| requests a quote / engages with an email. The legacy app had one
| copy-paste controller method per source; App\Services\Lead\EmailWebhookLeadService
| is driven by this map. Paths and the plain-text 'ok' response stay
| byte-identical to new-ahc.
|
|   email_key / phone_key  - request fields holding email / phone
|   phone_strip            - null (verbatim) | 'cel' (digits, drop leading 1)
|   cc_text / cc_name      - constant_contacts.email_text / webhook_name
|   time                   - 'now' | 'date_time' (payload Date + Time)
|   flag_column            - webhooks.<COL> once-per-day marker
|   lead_tag               - lead_tag stamped on a newly created lead
|   campaign_id            - campaign_id for a newly created lead
|   always_null_user       - always write user_id = null on the found-lead update
|   fields                 - lead column => payload key, applied on the
|                            found-lead update (contact/email use the resolved
|                            phone/email values, not raw keys)
|   new_fields             - lead column => payload key, applied on create
*/

return [
    'channels' => [

        'quote' => [
            'email_key' => 'Email',
            'phone_key' => 'Phone_Number',
            'phone_strip' => null,
            'cc_text' => 'Email Quote',
            'cc_name' => '$200 OFF',
            'time' => 'date_time',
            'flag_column' => 'QUOTE',
            'lead_tag' => 'Email Quote',
            'campaign_id' => 99,
            'always_null_user' => false,
            'fields' => ['first_name' => 'Name', 'email' => '@email', 'contact' => '@phone'],
            'new_fields' => ['first_name' => 'Name'],
        ],

        'pquote' => [
            'email_key' => 'Email',
            'phone_key' => 'Phone_Number',
            'phone_strip' => null,
            'cc_text' => 'Email Quote',
            'cc_name' => '$250 OFF +2 Months Three',
            'time' => 'now',
            'flag_column' => 'QUOTE',
            'lead_tag' => 'Email Quote',
            'campaign_id' => 99,
            'always_null_user' => false,
            'fields' => [
                'first_name' => 'First_Name', 'last_name' => 'Last_Name',
                'zip_code' => 'Zip_Code', 'email' => '@email', 'contact' => '@phone',
            ],
            'new_fields' => [
                'first_name' => 'First_Name', 'last_name' => 'Last_Name', 'zip_code' => 'Zip_Code',
            ],
        ],

        'cel' => [
            'email_key' => 'email',
            'phone_key' => 'phone',
            'phone_strip' => 'cel',
            'cc_text' => 'CEL',
            'cc_name' => 'CEL',
            'time' => 'now',
            'flag_column' => 'CEL',
            'lead_tag' => 'CEL',
            'campaign_id' => 99,
            'always_null_user' => true,
            'fields' => [
                'first_name' => 'first_name', 'last_name' => 'last_name',
                'email' => '@email', 'contact' => '@phone',
            ],
            'new_fields' => ['first_name' => 'first_name', 'last_name' => 'last_name'],
        ],
    ],
];
