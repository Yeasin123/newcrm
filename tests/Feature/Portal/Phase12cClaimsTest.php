<?php

use App\Livewire\Portal\Claims\Create;
use App\Livewire\Portal\Claims\Index;
use App\Models\AuthorizeAccount;
use App\Models\Brand;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Issue;
use App\Models\PaymentHistory;
use App\Models\System;
use App\Services\Billing\PaymentInput;
use App\Services\Portal\PortalClaimService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function portalMerchant(): AuthorizeAccount
{
    return AuthorizeAccount::create([
        'name' => 'Primary', 'environment' => 'sandbox',
        'api_login_id' => 'l', 'transaction_key' => 'k',
        'is_active' => true, 'is_default_charge' => true, 'weight' => 100,
    ]);
}

/** An eligible customer: active, signed, past the waiting period. */
function eligibleCustomer(array $o = []): Client
{
    return Client::factory()->create(array_merge([
        'status' => 1, 'call_fee' => '85', 'is_renew' => 0,
        'signature_date_time' => now()->subMonth()->toDateTimeString(),
        'start_date' => now()->subMonth()->toDateString(),
        'authorize_status' => null, 'agent_id' => null,
    ], $o));
}

function claimData(): array
{
    return [
        'system' => System::create(['title' => 'HVAC'])->id,
        'issue' => Issue::create(['title' => 'No cooling'])->id,
        'brand' => Brand::create(['title' => 'Carrier'])->id,
        'problem' => 'Blows warm air.',
        'last_time_working_detail' => 'Two days ago',
        'expiry' => '09/'.substr((string) (now()->year + 3), -2),
        'opaqueDataDescriptor' => 'COMMON.ACCEPT.INAPP.PAYMENT',
        'opaqueDataValue' => 'DRYRUN-token',
        'last4' => '1111',
    ];
}

beforeEach(function () {
    Http::preventStrayRequests();
    portalMerchant();
});

test('the claims list shows only the customer own claims', function () {
    $me = eligibleCustomer();
    $other = Client::factory()->create();
    Claim::create(['client_id' => $me->id, 'claim_no' => 'MINE01', 'problem' => 'x']);
    Claim::create(['client_id' => $other->id, 'claim_no' => 'THEIRS9', 'problem' => 'y']);

    Livewire::actingAs($me, 'user')->test(Index::class)
        ->assertSee('MINE01')
        ->assertDontSee('THEIRS9');
});

test('a claim detail page is 404 for someone else\'s claim', function () {
    $me = eligibleCustomer();
    $theirs = Claim::create(['client_id' => Client::factory()->create()->id, 'claim_no' => 'X', 'problem' => 'y']);

    $this->actingAs($me, 'user')
        ->get(route('portal.claims.show', $theirs->id))
        ->assertNotFound();
});

test('an eligible customer files a claim and is charged the call fee', function () {
    $client = eligibleCustomer(['call_fee' => '95']);

    $claim = app(PortalClaimService::class)->fileClaim($client, claimData(), new PaymentInput(
        opaqueDataDescriptor: 'COMMON.ACCEPT.INAPP.PAYMENT', opaqueDataValue: 'DRYRUN-token', explicitLast4: '1111',
    ));

    expect($claim->client_id)->toBe($client->id)
        ->and((int) $claim->status)->toBe(1)
        ->and((float) $claim->total_price)->toBe(-95.0);

    $history = PaymentHistory::where('claim_id', $claim->id)->first();
    expect($history)->not->toBeNull()
        ->and((int) $history->claim_payment)->toBe(1)
        ->and((float) $history->amount)->toBe(95.0)
        ->and($history->transaction_id)->toStartWith('DRYRUN-');
});

test('an unsigned agreement blocks filing', function () {
    $client = eligibleCustomer(['signature_date_time' => null]);

    expect(fn () => app(PortalClaimService::class)->assertCanFileClaim($client))
        ->toThrow(ValidationException::class, 'sign the agreement');
});

test('a suspended policy blocks filing', function () {
    $client = eligibleCustomer(['status' => 0]);

    expect(fn () => app(PortalClaimService::class)->assertCanFileClaim($client))
        ->toThrow(ValidationException::class, 'policy has expired');
});

test('an unpaid balance blocks filing', function () {
    $client = eligibleCustomer();
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 50, 'payment_status' => 0, 'payment_date' => now()]);

    expect(fn () => app(PortalClaimService::class)->assertCanFileClaim($client))
        ->toThrow(ValidationException::class, 'policy has expired');
});

test('the create screen surfaces the gate reason and refuses to file', function () {
    $client = eligibleCustomer(['status' => 0]);

    Livewire::actingAs($client, 'user')->test(Create::class)
        ->assertSet('gateError', fn ($v) => str_contains((string) $v, 'policy has expired'))
        ->call('file');

    expect(Claim::count())->toBe(0);
});

test('the create screen files a claim through to the detail page', function () {
    $client = eligibleCustomer();

    Livewire::actingAs($client, 'user')->test(Create::class)
        ->set('system', (string) claimData()['system'])
        ->set('issue', (string) System::first()->id) // any non-empty
        ->set('brand', (string) Brand::first()->id)
        ->set('problem', 'Blows warm air.')
        ->set('last_time_working_detail', 'Two days ago')
        ->set('expiry', '09/'.substr((string) (now()->year + 3), -2))
        ->set('opaqueDataDescriptor', 'COMMON.ACCEPT.INAPP.PAYMENT')
        ->set('opaqueDataValue', 'DRYRUN-token')
        ->set('last4', '1111')
        ->call('file')
        ->assertRedirect(route('portal.claims.show', Claim::first()->id));

    expect(Claim::where('client_id', $client->id)->count())->toBe(1);
});
