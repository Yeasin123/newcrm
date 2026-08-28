<?php

use App\Models\Entrance;
use App\Models\LeadDetail;
use App\Models\SmsText;
use App\Models\Webhook;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    SmsText::create(['sms_word' => 'yes']);
});

test('a numbered entrance swallows an unknown number and writes nothing', function () {
    $this->postJson('/third/entrance', ['number' => '15550000000', 'message' => 'yes'])
        ->assertOk()
        ->assertExactJson(['status' => 'success']);

    expect(Entrance::count())->toBe(0);
});

test('a numbered entrance records the reply and, on a keyword match, promotes the lead', function () {
    $lead = LeadDetail::factory()->create(['contact' => '5551230000', 'user_id' => null]);

    // legacy strips the first char of `number`, so send a leading digit.
    // legacy match is LOWER(sms_word) LIKE %message%, i.e. the stored word must
    // contain the whole inbound message — so an exact "yes" matches "yes".
    $this->postJson('/third/entrance', [
        'number' => 'X5551230000',
        'message' => 'yes',
        'campaign_id' => 42, 'channel_id' => 7, 'workspace_id' => 3,
    ])->assertOk()->assertExactJson(['status' => 'success']);

    $entrance = Entrance::firstOrFail();
    expect($entrance->lead_detail_id)->toBe($lead->id)
        ->and($entrance->webhook_name)->toBe('En')
        ->and($entrance->account_key)->toBe('EGKU6FXP8P1XP4480')
        ->and($entrance->campaign_id)->toBe(42);

    $lead->refresh();
    expect((int) $lead->sms_mail)->toBe(1)
        ->and((int) $lead->priority_indx)->toBe(1)
        ->and($lead->serial_number)->not->toBeNull()
        ->and($lead->msg_date)->not->toBeNull();

    expect(Webhook::where('lead_id', $lead->id)->where('EN', 'EN')->count())->toBe(1);
    Http::assertNothingSent(); // round-robin push is off by default
});

test('a non-matching reply is recorded but does not promote the lead', function () {
    $lead = LeadDetail::factory()->create(['contact' => '5559990000']);

    $this->postJson('/fourth-entrance', ['number' => 'X5559990000', 'message' => 'STOP texting me'])
        ->assertOk();

    expect(Entrance::where('lead_detail_id', $lead->id)->count())->toBe(1)
        ->and(Webhook::where('lead_id', $lead->id)->count())->toBe(0)
        ->and($lead->fresh()->sms_mail)->toBeNull();
});

test('the once-per-day webhook flag is not duplicated for repeated matches', function () {
    $lead = LeadDetail::factory()->create(['contact' => '5551112222']);

    foreach (range(1, 3) as $_) {
        $this->postJson('/fifth-entrance', ['number' => 'X5551112222', 'message' => 'yes'])->assertOk();
    }

    expect(Entrance::where('lead_detail_id', $lead->id)->count())->toBe(3)
        ->and(Webhook::where('lead_id', $lead->id)->where('EN', 'EN')->count())->toBe(1);
});

test('the mm-webhook returns a trace id and strips a leading +1', function () {
    $lead = LeadDetail::factory()->create(['contact' => '5553334444']);

    $res = $this->postJson('/mm-webhook', ['number' => '+15553334444', 'message' => 'yes'])
        ->assertOk();

    expect($res->json('status'))->toBe('success')
        ->and($res->json('trace_id'))->toBeString();

    $entrance = Entrance::where('lead_detail_id', $lead->id)->firstOrFail();
    expect($entrance->webhook_name)->toBe('MM')
        ->and($entrance->contact)->toBe('5553334444');
    expect(Webhook::where('lead_id', $lead->id)->where('MM', 'MM')->count())->toBe(1);
});

test('the yair webhook rejects an empty payload and handles a normalized match', function () {
    $this->postJson('/new/yair-webhook/link', [])
        ->assertStatus(400)
        ->assertJson(['status' => 'error', 'message' => 'Invalid or empty payload']);

    $lead = LeadDetail::factory()->create(['contact' => '5556667777']);

    $this->postJson('/new/yair-webhook/link', ['number' => '1-555-666-7777', 'message' => '  Yes  '])
        ->assertOk()
        ->assertJson(['status' => 'success']);

    $entrance = Entrance::where('lead_detail_id', $lead->id)->firstOrFail();
    expect($entrance->webhook_name)->toBe('YAIR')
        ->and($entrance->contact)->toBe('5556667777');
    expect(Webhook::where('lead_id', $lead->id)->where('YAIR', 'YAIR')->count())->toBe(1);
});
