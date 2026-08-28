<?php

use App\Livewire\SalesmanMessages\Inbox;
use App\Models\SalesmanMessage;
use App\Models\User;
use App\Services\Messaging\SalesmanMessageService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.telnyx.from_number' => '5550000000']);
});

function salesman(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'role_id' => '7', 'status' => 1, 'contact' => '5551234567',
    ], $attrs));
}

test('sending is recorded as failed when the SMS transport is off', function () {
    config(['telephony.sms_enabled' => false]);
    $admin = User::factory()->superAdmin()->create();
    $rep = salesman();

    $message = app(SalesmanMessageService::class)->send($admin->id, $rep, 'Call me back');

    expect($message->direction)->toBe('outbound')
        ->and($message->status)->toBe('failed')
        ->and($message->error_message)->not->toBeNull()
        ->and($message->to_number)->toBe('+15551234567');
    Http::assertNothingSent();
});

test('sending goes out through Telnyx when the transport is on', function () {
    config(['comms.enabled' => true, 'comms.sms' => true, 'telephony.sms_enabled' => true, 'services.telnyx.api_key' => 'KEY']);
    Http::fake(['*api.telnyx.com/*' => Http::response(['data' => ['id' => 'tx-9']], 200)]);

    $admin = User::factory()->superAdmin()->create();
    $message = app(SalesmanMessageService::class)->send($admin->id, salesman(), 'Ping');

    expect($message->status)->toBe('sent')->and($message->telnyx_message_id)->toBe('tx-9');
});

test('the inbound webhook stores a received message against the salesman', function () {
    $admin = User::factory()->superAdmin()->create();
    $rep = salesman(['contact' => '5551234567']);

    $this->postJson('/telnyx/salesman-messages/webhook', [
        'data' => ['payload' => [
            'id' => 'in-1',
            'text' => 'On my way',
            'from' => ['phone_number' => '+15551234567'],
            'to' => [['phone_number' => '+15550000000']],
        ]],
    ])->assertOk()->assertExactJson(['status' => 'received']);

    $message = SalesmanMessage::where('salesman_id', $rep->id)->firstOrFail();
    expect($message->direction)->toBe('inbound')
        ->and($message->status)->toBe('received')
        ->and($message->body)->toBe('On my way')
        ->and($message->admin_id)->toBe($admin->id);
});

test('the inbound webhook ignores an empty payload and an unknown number', function () {
    User::factory()->superAdmin()->create();

    $this->postJson('/telnyx/salesman-messages/webhook', ['data' => ['payload' => ['text' => '']]])
        ->assertOk()->assertExactJson(['status' => 'ignored']);

    $this->postJson('/telnyx/salesman-messages/webhook', ['data' => ['payload' => [
        'text' => 'hi', 'from' => ['phone_number' => '+19998887777'],
    ]]])->assertOk()->assertExactJson(['status' => 'salesman_not_found']);
});

test('the inbox is superadmin-only and marks a thread read on open', function () {
    $admin = User::factory()->superAdmin()->create();
    $rep = salesman();
    SalesmanMessage::create([
        'admin_id' => $admin->id, 'salesman_id' => $rep->id, 'direction' => 'inbound',
        'status' => 'received', 'body' => 'unread', 'read_at' => null,
    ]);

    Livewire::actingAs(User::factory()->withRole(['Customers'])->create())
        ->test(Inbox::class)->assertForbidden();

    Livewire::actingAs($admin)
        ->test(Inbox::class)
        ->assertOk()
        ->call('selectSalesman', $rep->id);

    expect(SalesmanMessage::where('salesman_id', $rep->id)->whereNull('read_at')->count())->toBe(0);
});
