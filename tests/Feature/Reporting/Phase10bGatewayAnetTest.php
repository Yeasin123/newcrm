<?php

use App\Livewire\Reporting\AnetHealth;
use App\Livewire\Reporting\GatewayMonitor;
use App\Models\AnetWebhookEvent;
use App\Models\AuthorizeAccount;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Models\User;
use App\Services\Reporting\AnetReportService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['reporting.anet_live_checks' => false]);
});

function anetAcct(string $name = 'M1'): AuthorizeAccount
{
    return AuthorizeAccount::create(['name' => $name, 'environment' => 'sandbox', 'api_login_id' => 'l', 'transaction_key' => 'k', 'is_active' => true, 'weight' => 100]);
}

test('the gateway monitor lists and filters webhook events', function () {
    $a = anetAcct('Alpha');
    $b = anetAcct('Beta');
    AnetWebhookEvent::create(['authorize_account_id' => $a->id, 'event_id' => 'e1', 'event_type' => 'net.authorize.customer.subscription.suspended', 'payload_id' => 'SUB-1']);
    AnetWebhookEvent::create(['authorize_account_id' => $b->id, 'event_id' => 'e2', 'event_type' => 'net.authorize.payment.authcapture.created', 'payload_id' => 'TX-2']);

    Livewire::actingAs(User::factory()->withRole(['Settings'])->create())
        ->test(GatewayMonitor::class)
        ->assertOk()
        ->assertSee('SUB-1')->assertSee('TX-2')
        ->set('event_type', 'suspended')
        ->assertSee('SUB-1')->assertDontSee('TX-2')
        ->set('event_type', '')
        ->set('authorize_account_id', $b->id)
        ->assertSee('TX-2')->assertDontSee('SUB-1');
});

test('the ANet health screen summarises each merchant account and its failed subscriptions', function () {
    $acc = anetAcct('Gamma');
    $client = Client::factory()->create(['status' => 0, 'authorize_account_id' => $acc->id]);

    PaymentHistory::create(['client_id' => $client->id, 'authorize_account_id' => $acc->id, 'subscription_id' => 'SUB-FAIL', 'amount' => 40, 'payment_status' => 0, 'claim_payment' => 0, 'is_custom' => 0, 'payment_date' => now()->subMonth()]);
    PaymentHistory::create(['client_id' => $client->id, 'authorize_account_id' => $acc->id, 'subscription_id' => 'SUB-OK', 'amount' => 40, 'payment_status' => 1, 'claim_payment' => 0, 'is_custom' => 0, 'payment_date' => now()->subMonth()]);

    Livewire::actingAs(User::factory()->withRole(['Settings'])->create())
        ->test(AnetHealth::class)
        ->assertOk()
        ->assertSee('Gamma')
        ->assertSee('SUB-FAIL')       // local_status defaults to 'failed'
        ->assertDontSee('SUB-OK')
        ->assertSee('Needs review')   // live checks off -> verdict is warning
        ->set('local_status', 'paid')
        ->assertSee('SUB-OK')->assertDontSee('SUB-FAIL');
});

test('checkSubscription is a no-op when live checks are disabled', function () {
    $acc = anetAcct();
    $client = Client::factory()->create();
    $payment = PaymentHistory::create(['client_id' => $client->id, 'authorize_account_id' => $acc->id, 'subscription_id' => 'SUB-9', 'amount' => 10, 'payment_status' => 0, 'is_custom' => 0, 'claim_payment' => 0]);

    $check = app(AnetReportService::class)->checkSubscription($payment->load('userInfo'));

    expect($check['status'])->toBe('skipped');
    expect(app(AnetReportService::class)->buildMismatchStatus($payment, $check)['level'])->toBe('warning');
    Http::assertNothingSent();
});

test('the report screens are gated by the Settings permission', function () {
    $nobody = User::factory()->withRole(['Customers'])->create();

    Livewire::actingAs($nobody)->test(GatewayMonitor::class)->assertForbidden();
    Livewire::actingAs($nobody)->test(AnetHealth::class)->assertForbidden();
});
