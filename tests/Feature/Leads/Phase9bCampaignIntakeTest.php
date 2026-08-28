<?php

use App\Models\LeadDetail;

test('the ahc campaign link creates an Inquiry lead and strips phone dashes', function () {
    $this->post('/ahc/campaign/lead-store', [
        'first_name' => '  Jane ', 'last_name' => 'Doe',
        'phone_number' => '555-321-9000', 'email' => 'jane@example.com',
        'state' => 'TX',
    ])->assertOk()->assertContent('ok');

    $lead = LeadDetail::firstOrFail();
    expect($lead->contact)->toBe('5553219000')
        ->and($lead->first_name)->toBe('Jane')
        ->and((int) $lead->campaign_id)->toBe(157)
        ->and($lead->lead_tag)->toBe('Inquiry')
        ->and($lead->state)->toBe('TX');
});

test('a re-post to the same campaign link updates rather than duplicates', function () {
    $payload = ['phone_number' => '5553219000', 'first_name' => 'Jane', 'campaign' => 'x'];
    $this->post('/ahc/campaign/lead-store', $payload)->assertOk();
    $this->post('/ahc/campaign/lead-store', ['phone_number' => '5553219000', 'first_name' => 'Janet'])->assertOk();

    expect(LeadDetail::where('contact', '5553219000')->count())->toBe(1)
        ->and(LeadDetail::where('contact', '5553219000')->value('first_name'))->toBe('Janet');
});

test('the lcl campaign link does not set the Inquiry tag', function () {
    $this->post('/lcl/campaign/lead-store', ['phone_number' => '5550001111', 'first_name' => 'Al'])
        ->assertOk()->assertContent('ok');

    $lead = LeadDetail::where('contact', '5550001111')->firstOrFail();
    expect((int) $lead->campaign_id)->toBe(159)
        ->and($lead->lead_tag)->toBeNull();
});

test('an empty phone yields empty_data and writes nothing', function () {
    $this->post('/gbl/campaign/lead-store', ['first_name' => 'NoPhone'])
        ->assertOk()->assertContent('empty_data');

    expect(LeadDetail::count())->toBe(0);
});

test('the modanize link always creates a fresh MD lead', function () {
    foreach (range(1, 2) as $_) {
        $this->post('/modanize/campaign/lead-store', [
            'first_name' => 'Mod', 'last_name' => 'Ern', 'contact' => '555-777-8888',
            'modernize_id' => 'MID-42', 'state' => 'FL',
        ])->assertOk()->assertContent('ok');
    }

    $leads = LeadDetail::where('contact', '5557778888')->get();
    expect($leads)->toHaveCount(2);
    expect((int) $leads->first()->campaign_id)->toBe(139)
        ->and((int) $leads->first()->progress_bar)->toBe(2)
        ->and($leads->first()->lead_type)->toBe('MD')
        ->and($leads->first()->modernize_id)->toBe('MID-42');
});

test('the modanize link skips a payload with no name and no phone', function () {
    $this->post('/modanize/campaign/lead-store', ['email' => 'x@example.com'])
        ->assertOk()->assertContent('empty_data');

    expect(LeadDetail::count())->toBe(0);
});
