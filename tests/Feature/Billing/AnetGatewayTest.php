<?php

use App\Exceptions\AnetApiException;
use App\Models\AuthorizeAccount;
use App\Models\Client;
use App\Services\Billing\Anet\AnetClient;
use App\Services\Billing\Anet\ArbGateway;
use App\Services\Billing\Anet\CimGateway;
use App\Services\Billing\Anet\TransactionGateway;
use App\Services\Billing\AuthorizeAccountSelector;
use App\Services\Billing\DryRunPaymentGateway;
use App\Services\Billing\LivePaymentGateway;
use App\Services\Billing\PaymentGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function acc(array $o = []): AuthorizeAccount
{
    return AuthorizeAccount::create(array_merge([
        'name' => 'M', 'environment' => 'sandbox',
        'api_login_id' => 'login', 'transaction_key' => 'key',
        'is_active' => true, 'weight' => 100,
    ], $o));
}

/** Fake the single Authorize.Net endpoint with a JSON body (optionally BOM-prefixed). */
function fakeAnet(array $body, bool $bom = false): void
{
    Http::fake([
        '*authorize.net/*' => Http::response(($bom ? "\xEF\xBB\xBF" : '').json_encode($body), 200),
    ]);
}

beforeEach(fn () => Http::preventStrayRequests());

test('AnetClient strips the BOM and returns the decoded Ok body', function () {
    fakeAnet(['messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001', 'text' => 'Successful.']]], 'customerProfileId' => '123'], bom: true);

    $out = app(AnetClient::class)->call(acc(), 'createCustomerProfileRequest', []);

    expect($out['customerProfileId'])->toBe('123');
});

test('AnetClient throws AnetApiException with the gateway code/text on Error', function () {
    fakeAnet(['messages' => ['resultCode' => 'Error', 'message' => [['code' => 'E00003', 'text' => 'Bad request.']]]]);

    expect(fn () => app(AnetClient::class)->call(acc(), 'createCustomerProfileRequest', []))
        ->toThrow(AnetApiException::class, 'Authorize.Net error [E00003]: Bad request.');
});

test('CimGateway parses the profile ids from a card create', function () {
    fakeAnet([
        'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001', 'text' => 'ok']]],
        'customerProfileId' => 'CP-1',
        'customerPaymentProfileIdList' => ['PP-1'],
    ]);

    $client = Client::factory()->create();
    $ids = app(CimGateway::class)->createProfileFromCard(acc(), $client, '4111111111111111', '12', '2030');

    expect($ids)->toBe(['customer_profile_id' => 'CP-1', 'payment_profile_id' => 'PP-1']);
});

test('ArbGateway returns the subscription id on create', function () {
    fakeAnet(['messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001', 'text' => 'ok']]], 'subscriptionId' => 'SUB-9']);

    $id = app(ArbGateway::class)->createSubscription(acc(), 'CP-1', 'PP-1', Carbon::parse('2026-09-01'), 49.99);

    expect($id)->toBe('SUB-9');
});

test('ArbGateway retries on E00040 then succeeds', function () {
    Http::fake([
        '*authorize.net/*' => Http::sequence()
            ->push(json_encode(['messages' => ['resultCode' => 'Error', 'message' => [['code' => 'E00040', 'text' => 'not found']]]]), 200)
            ->push(json_encode(['messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001', 'text' => 'ok']]], 'subscriptionId' => 'SUB-RETRY']), 200),
    ]);

    $id = app(ArbGateway::class)->createSubscriptionWithRetry(
        acc(), 'CP', 'PP', Carbon::parse('2026-09-01'), 20,
        initialSleepSeconds: 0, retrySleepSeconds: 0,
    );

    expect($id)->toBe('SUB-RETRY');
});

test('ArbGateway maps I00002 to already_canceled', function () {
    fakeAnet(['messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00002', 'text' => 'already cancelled']]]]);

    expect(app(ArbGateway::class)->cancelSubscription(acc(), 'SUB-1'))->toBe('already_canceled');
});

test('TransactionGateway returns the transaction id on an approved charge', function () {
    fakeAnet([
        'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001', 'text' => 'ok']]],
        'transactionResponse' => ['responseCode' => '1', 'transId' => 'TXN-1', 'authCode' => 'ABC123'],
    ]);

    $out = app(TransactionGateway::class)->chargeCustomerProfile(acc(), 'CP', 'PP', 30.0);

    expect($out['transaction_id'])->toBe('TXN-1')->and($out['auth_code'])->toBe('ABC123');
});

test('TransactionGateway throws on a declined charge', function () {
    fakeAnet([
        'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001', 'text' => 'ok']]],
        'transactionResponse' => ['responseCode' => '2', 'errors' => [['errorCode' => '2', 'errorText' => 'This transaction has been declined.']]],
    ]);

    expect(fn () => app(TransactionGateway::class)->chargeCustomerProfile(acc(), 'CP', 'PP', 30.0))
        ->toThrow(AnetApiException::class, 'declined');
});

test('the account selector prefers the default-charge account, else weights active ones', function () {
    Http::fake();
    $default = acc(['name' => 'Default', 'is_default_charge' => true]);
    acc(['name' => 'Other', 'weight' => 1]);

    expect(app(AuthorizeAccountSelector::class)->pickForCharge()->id)->toBe($default->id);

    $default->update(['is_default_charge' => false]);
    expect(app(AuthorizeAccountSelector::class)->weightedActive())->not->toBeNull();
});

test('the default payment gateway is dry-run and makes no HTTP calls', function () {
    Http::fake(); // any stray call would be recorded

    $gateway = app(PaymentGateway::class);
    expect($gateway)->toBeInstanceOf(DryRunPaymentGateway::class)
        ->and($gateway->isLive())->toBeFalse();

    $account = acc();
    $client = Client::factory()->create();

    $profile = $gateway->createCardProfile($account, $client, '4111111111111111', '12', '2030');
    $sub = $gateway->createSubscriptionFromProfile(
        $account, $profile['customer_profile_id'], $profile['payment_profile_id'],
        Carbon::parse('2026-09-01'), 49.99, 1, 'months', 999,
    );

    expect($profile['customer_profile_id'])->toStartWith('DRYRUN-CP-')
        ->and($sub)->toStartWith('DRYRUN-SUB-')
        ->and($profile['last4'])->toBe('1111');
    Http::assertNothingSent();
});

test('flipping both billing flags to live swaps in the live gateway', function () {
    config(['billing.driver' => 'live', 'billing.processing_mode' => 'live']);

    expect(app(PaymentGateway::class))->toBeInstanceOf(LivePaymentGateway::class);
});
