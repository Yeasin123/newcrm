<?php

use App\Models\AnetWebhookEvent;
use App\Models\AuthorizeAccount;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Services\Billing\AnetSignatureVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

const SIG_KEY = 'DEADBEEFDEADBEEFDEADBEEFDEADBEEF';

function anetAccount(array $overrides = []): AuthorizeAccount
{
    return AuthorizeAccount::create(array_merge([
        'name' => 'Test Merchant',
        'environment' => 'sandbox',
        'api_login_id' => 'login123',
        'transaction_key' => 'txnkey123',
        'signature_key' => SIG_KEY,
        'is_active' => true,
        'weight' => 100,
    ], $overrides));
}

/** POST a JSON body to a notify route with a valid (or forced-invalid) signature. */
function postNotify(string $path, array $body, ?string $sigKey = SIG_KEY): TestResponse
{
    $raw = json_encode($body);
    $header = $sigKey === null
        ? []
        : ['x-anet-signature' => 'sha512='.hash_hmac('sha512', $raw, $sigKey)];

    return test()->call('POST', $path, [], [], [], transformHeadersToServerVars($header), $raw);
}

function transformHeadersToServerVars(array $headers): array
{
    $server = [];
    foreach ($headers as $key => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
    }
    $server['CONTENT_TYPE'] = 'application/json';

    return $server;
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('the signature verifier matches the account whose key signed the body', function () {
    $acc = anetAccount();
    anetAccount(['name' => 'Other', 'signature_key' => 'somethingelse']);

    $raw = '{"hello":"world"}';
    $sig = 'sha512='.hash_hmac('sha512', $raw, SIG_KEY);

    expect(app(AnetSignatureVerifier::class)->detectAccount($raw, $sig)?->id)->toBe($acc->id)
        ->and(app(AnetSignatureVerifier::class)->detectAccount($raw, 'sha512=deadbeef'))->toBeNull()
        ->and(app(AnetSignatureVerifier::class)->detectAccount($raw, 'not-a-sig'))->toBeNull()
        ->and(app(AnetSignatureVerifier::class)->detectAccount($raw, null))->toBeNull();
});

test('every notify endpoint returns 200 even on a bad signature', function () {
    anetAccount();

    postNotify('/api/transaction/create/notify', ['id' => 'e1'], sigKey: null)->assertOk();
    postNotify('/api/transaction/suspend/notify', ['id' => 'e2'], sigKey: 'wrong-key')->assertOk()->assertJson(['status' => 'success']);
    postNotify('/api/transaction/terminated/notify', ['id' => 'e3'], sigKey: 'wrong-key')->assertOk();
});

test('a duplicate event id is ignored', function () {
    $acc = anetAccount();
    $body = ['id' => 'evt-dup', 'eventType' => 'net.authorize.payment.capture.created', 'payload' => ['id' => 'txn1']];

    postNotify('/api/transaction/create/notify', $body)->assertOk()->assertJson(['status' => 'success']);
    postNotify('/api/transaction/create/notify', $body)->assertOk()->assertJson(['status' => 'duplicate_ignored']);

    expect(AnetWebhookEvent::where('event_id', 'evt-dup')->count())->toBe(1);
});

test('a subscription suspended webhook fails the customer and records a failed attempt', function () {
    $acc = anetAccount();
    $client = Client::factory()->monthly()->create(['status' => 1]);
    $seed = PaymentHistory::create([
        'client_id' => $client->id, 'subscription_id' => 'sub-123', 'amount' => 49.99,
        'payment_status' => 1, 'status' => 1, 'claim_payment' => 0, 'is_custom' => 0,
        'payment_type' => 0, 'authorize_account_id' => $acc->id,
        'payment_date' => now()->subDays(3)->format('Y-m-d H:i:s'),
    ]);

    postNotify('/api/transaction/suspend/notify', [
        'id' => 'evt-susp-1',
        'eventType' => 'net.authorize.customer.subscription.suspended',
        'eventDate' => now()->toIso8601String(),
        'payload' => ['id' => 'sub-123', 'amount' => 49.99],
    ])->assertOk();

    $client->refresh();
    expect($client->status)->toBe(0)
        ->and($client->payment_faild_date)->not->toBeNull()
        ->and(PaymentHistory::where('client_id', $client->id)->where('payment_status', 0)->where('is_custom', 0)->exists())->toBeTrue()
        ->and(AnetWebhookEvent::where('event_id', 'evt-susp-1')->exists())->toBeTrue();
});

test('a terminated webhook fails the customer too', function () {
    $acc = anetAccount();
    $client = Client::factory()->monthly()->create(['status' => 1]);
    PaymentHistory::create([
        'client_id' => $client->id, 'subscription_id' => 'sub-999', 'amount' => 30,
        'payment_status' => 1, 'status' => 1, 'claim_payment' => 0, 'is_custom' => 0,
        'payment_type' => 0, 'authorize_account_id' => $acc->id,
        'payment_date' => now()->subDays(2)->format('Y-m-d H:i:s'),
    ]);

    postNotify('/api/transaction/terminated/notify', [
        'id' => 'evt-term-1',
        'eventType' => 'net.authorize.customer.subscription.terminated',
        'eventDate' => now()->toIso8601String(),
        'payload' => ['id' => 'sub-999'],
    ])->assertOk();

    expect($client->fresh()->status)->toBe(0);
});

test('missed monthly cycles are backfilled as unpaid rows', function () {
    $acc = anetAccount();
    $client = Client::factory()->monthly()->create(['status' => 1]);
    PaymentHistory::create([
        'client_id' => $client->id, 'subscription_id' => 'sub-gap', 'amount' => 20,
        'payment_status' => 1, 'status' => 1, 'claim_payment' => 0, 'is_custom' => 0,
        'payment_type' => 0, 'authorize_account_id' => $acc->id,
        'payment_date' => now()->subMonths(3)->startOfMonth()->format('Y-m-d H:i:s'),
    ]);

    postNotify('/api/transaction/suspend/notify', [
        'id' => 'evt-gap-1',
        'eventType' => 'net.authorize.customer.subscription.suspended',
        'eventDate' => now()->toIso8601String(),
        'payload' => ['id' => 'sub-gap'],
    ])->assertOk();

    // 3-month gap → at least the intervening month(s) filled in, all unpaid.
    expect(PaymentHistory::where('subscription_id', 'sub-gap')->where('payment_status', 0)->count())
        ->toBeGreaterThanOrEqual(2);
});
