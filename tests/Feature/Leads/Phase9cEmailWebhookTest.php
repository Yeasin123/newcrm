<?php

use App\Models\ConstantContact;
use App\Models\Entrance;
use App\Models\LeadDetail;
use App\Models\SmsText;
use App\Models\Webhook;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

test('the quote webhook creates a lead, a constant-contact row and a QUOTE day flag', function () {
    $this->post('/quote/email/webhook', [
        'Name' => 'Ada Quote', 'Email' => 'ada@example.com', 'Phone_Number' => '5551112222',
        'Date' => '2026-01-15', 'Time' => '14:30',
    ])->assertOk()->assertContent('ok');

    $lead = LeadDetail::firstOrFail();
    expect($lead->first_name)->toBe('Ada Quote')
        ->and((int) $lead->campaign_id)->toBe(99)
        ->and((int) $lead->sms_mail)->toBe(2)
        ->and($lead->lead_tag)->toBe('Email Quote');

    $cc = ConstantContact::where('lead_id', $lead->id)->firstOrFail();
    expect($cc->email_text)->toBe('Email Quote')
        ->and($cc->webhook_name)->toBe('$200 OFF')
        ->and($cc->email_time)->toBe('01/15/2026 14:30');

    expect(Webhook::where('lead_id', $lead->id)->where('QUOTE', 'QUOTE')->count())->toBe(1);
});

test('the quote webhook refreshes an existing lead matched by email', function () {
    $lead = LeadDetail::factory()->create(['email' => 'existing@example.com', 'first_name' => 'Old', 'user_id' => null]);

    $this->post('/quote/email/webhook', [
        'Name' => 'New Name', 'Email' => 'existing@example.com', 'Phone_Number' => '5559998888',
        'Date' => '2026-02-01', 'Time' => '09:00',
    ])->assertOk();

    $lead->refresh();
    expect($lead->first_name)->toBe('New Name')
        ->and($lead->contact)->toBe('5559998888')
        ->and((int) $lead->sms_mail)->toBe(2);
    expect(LeadDetail::count())->toBe(1);
});

test('the pquote webhook carries last name and zip and its own offer name', function () {
    $lead = LeadDetail::factory()->create(['contact' => '5553334444']);

    $this->post('/pquote/email/webhook', [
        'First_Name' => 'Pat', 'Last_Name' => 'Quotic', 'Zip_Code' => '77001',
        'Email' => 'pat@example.com', 'Phone_Number' => '5553334444',
    ])->assertOk();

    $lead->refresh();
    expect($lead->last_name)->toBe('Quotic')
        ->and($lead->zip_code)->toBe('77001');
    expect(ConstantContact::where('lead_id', $lead->id)->value('webhook_name'))->toBe('$250 OFF +2 Months Three');
});

test('the CEL webhook normalises the phone and tags a new lead CEL', function () {
    $this->post('/cel/webhook', [
        'first_name' => 'Cee', 'last_name' => 'Ell', 'email' => 'cee@example.com',
        'phone' => '+1 (555) 222-3333',
    ])->assertOk()->assertContent('ok');

    $lead = LeadDetail::firstOrFail();
    expect($lead->contact)->toBe('5552223333')
        ->and($lead->lead_tag)->toBe('CEL');
    expect(Webhook::where('lead_id', $lead->id)->where('CEL', 'CEL')->count())->toBe(1);
});

test('the campaign webhook returns json and creates a campaign-162 lead', function () {
    $res = $this->postJson('/campaign/webhook', [
        'first_name' => 'Cam', 'last_name' => 'Paign', 'email' => 'cam@example.com',
        'phone' => '+1-555-444-0000',
    ])->assertOk();

    $lead = LeadDetail::firstOrFail();
    expect($res->json('status'))->toBe('success')
        ->and($res->json('lead_id'))->toBe($lead->id)
        ->and((int) $lead->campaign_id)->toBe(162)
        ->and($lead->contact)->toBe('555-444-0000');
});

test('the change-email webhook validates input and updates the lead', function () {
    $this->postJson('/change/email/webhook', ['contact' => '5551230000'])
        ->assertStatus(400)->assertSee('missing data');

    $lead = LeadDetail::factory()->create(['contact' => '5551230000', 'email' => 'stale@example.com']);

    $this->post('/change/email/webhook', ['contact' => '15551230000', 'email' => 'fresh@example.com'])
        ->assertOk()->assertContent('ok');

    $lead->refresh();
    expect($lead->email)->toBe('fresh@example.com')
        ->and($lead->lead_tag)->toBe('Inquiry');
});

test('the second/new webhook records an LG entrance reply', function () {
    SmsText::create(['sms_word' => 'yes']);
    $lead = LeadDetail::factory()->create(['contact' => '5551239999']);

    $this->postJson('/second/new/webhook', [
        'data' => ['from_address' => '+15551239999', 'message' => 'yes', 'message_body' => 'YES sign me up'],
    ])->assertOk()->assertContent('ok');

    $entrance = Entrance::where('lead_detail_id', $lead->id)->firstOrFail();
    expect($entrance->webhook_name)->toBe('LG')
        ->and($entrance->message)->toBe('YES sign me up');
    expect(Webhook::where('lead_id', $lead->id)->where('LG', 'LG')->count())->toBe(1);
});

test('the new/email link-click webhook records an Instantly engagement', function () {
    $lead = LeadDetail::factory()->create(['email' => 'click@example.com', 'user_id' => null]);

    $this->postJson('/new/email/webhook', [
        'output' => ['Item 1' => 'click@example.comemail_link_clicked2026-01-02T03:04:05.000Zwinter-campaign'],
    ])->assertOk()->assertContent('ok');

    $cc = ConstantContact::where('lead_id', $lead->id)->firstOrFail();
    expect($cc->email_text)->toBe('Clicked Email')
        ->and($cc->webhook_name)->toBe('Inst')
        ->and($cc->campaign)->toBe('winter-campaign');

    $lead->refresh();
    expect((int) $lead->mail)->toBe(1);
    expect(Webhook::where('lead_id', $lead->id)->where('INST', 'INST')->count())->toBe(1);
});
