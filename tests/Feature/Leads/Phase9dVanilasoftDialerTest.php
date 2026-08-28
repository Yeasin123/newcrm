<?php

use App\Events\VanilasoftDialCall;
use App\Models\BuyNumber;
use App\Models\LeadDetail;
use App\Models\LeadStatus;
use App\Models\Note;
use App\Models\TrackButton;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

test('user/info rotates a caller id by area code, locks it and fires the event', function () {
    Event::fake([VanilasoftDialCall::class]);

    User::factory()->create(['v_salesman_id' => 'S1', 'ext_number' => '101']);
    LeadDetail::factory()->create(['contact' => '5552345678', 'state' => 'TX']);
    $match = BuyNumber::create(['contact_no' => '5559990000', 'state' => 'NV']);
    BuyNumber::create(['contact_no' => '2125550000', 'state' => 'NY']);

    $this->postJson('/vanilasoft/user/info', [
        'contact' => ['contact' => '(555) 234-5678', 'salesman' => 'S1'],
    ])->assertOk()->assertSee('ok');

    expect($match->fresh()->lock_number)->toBe('Lock');
    Event::assertDispatched(VanilasoftDialCall::class);
    Http::assertNothingSent(); // 3CX push disabled by default
});

test('user/info falls back to a state number when no area code matches', function () {
    Event::fake([VanilasoftDialCall::class]);

    User::factory()->create(['v_salesman_id' => 'S1']);
    LeadDetail::factory()->create(['contact' => '9998887777', 'state' => 'TX']);
    BuyNumber::create(['contact_no' => '4695550000', 'state' => 'TX']);

    $this->postJson('/vanilasoft/user/info', [
        'contact' => ['contact' => '999-888-7777', 'salesman' => 'S1', 'state' => 'TX'],
    ])->assertOk();

    Event::assertDispatched(VanilasoftDialCall::class);
});

test('check/salesman/id echoes the id only for the matching authenticated user', function () {
    $user = User::factory()->create(['v_salesman_id' => 'S9']);

    $this->postJson('/check/salesman/id', ['salesman_id' => 'S9'])->assertStatus(401);

    $this->actingAs($user)->postJson('/check/salesman/id', ['salesman_id' => 'S9'])
        ->assertOk()->assertContent('"S9"');
    $this->actingAs($user)->postJson('/check/salesman/id', ['salesman_id' => 'WRONG'])
        ->assertOk()->assertContent('null');
});

test('viewboard/info records a Sale disposition, note and lead reassignment', function () {
    LeadStatus::create(['title' => 'Sale']);
    $lead = LeadDetail::factory()->create(['contact' => '5551112222']);
    $rep = User::factory()->create(['v_salesman_id' => 'S1']);

    $this->postJson('/vanilasoft/viewboard/info', [
        'contact' => [
            'status' => 'Sale', 'PHONE' => '5551112222', 'userid' => 'S1',
            'notes' => 'Closed the deal', 'callduration' => '0:03:30',
            'date' => '01/15/2026', 'time' => '02:30PM',
        ],
    ])->assertOk()->assertSee('ok');

    $status = LeadStatus::where('title', 'Sale')->value('id');
    $lead->refresh();
    expect((int) $lead->status)->toBe((int) $status)
        ->and((string) $lead->user_id)->toBe((string) $rep->id);

    $tb = TrackButton::where('lead_id', $lead->id)->firstOrFail();
    expect(json_decode($tb->button_text, true))->toBe(['Sale' => 1])
        ->and((int) $tb->duration)->toBe(210);

    expect(Note::where('lead_id', $lead->id)->where('notes', 'Closed the deal')->exists())->toBeTrue();
    Http::assertNothingSent(); // Zapier sale post disabled by default
});

test('viewboard/info returns an empty 200 when the disposition cannot be matched', function () {
    LeadStatus::create(['title' => 'Sale']);

    $res = $this->postJson('/vanilasoft/viewboard/info', [
        'contact' => [
            'status' => 'Sale', 'PHONE' => '0000000000', 'userid' => 'nobody',
            'notes' => 'x', 'callduration' => '0:0:5', 'date' => '01/15/2026', 'time' => '02:30PM',
        ],
    ])->assertOk();

    expect($res->getContent())->toBe('')
        ->and(TrackButton::count())->toBe(0);
});

test('viewboard/info maps a raw dialer status through findStatus', function () {
    LeadStatus::create(['title' => 'No Answer']);
    $lead = LeadDetail::factory()->create(['contact' => '5553334444']);
    User::factory()->create(['v_salesman_id' => 'S1']);

    $this->postJson('/vanilasoft/viewboard/info', [
        'contact' => [
            'status' => 'No Answer 1', 'PHONE' => '5553334444', 'userid' => 'S1',
            'notes' => null, 'callduration' => '0:0:0', 'date' => '01/15/2026', 'time' => '02:30PM',
        ],
    ])->assertOk()->assertSee('ok');

    $status = LeadStatus::where('title', 'No Answer')->value('id');
    expect((int) $lead->fresh()->status)->toBe((int) $status)
        ->and(Note::where('lead_id', $lead->id)->exists())->toBeFalse(); // null comments -> no note
});
