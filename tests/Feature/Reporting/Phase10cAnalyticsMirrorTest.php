<?php

use App\Models\Claim;
use App\Models\Client;
use App\Models\LeadDetail;
use App\Models\PaymentHistory;
use App\Services\Analytics\AnalyticsSyncService;
use Illuminate\Support\Facades\DB;

/** Point the analytics connection at the test sqlite database. */
function enableMirror(): void
{
    config(['analytics.mirror_enabled' => true, 'analytics.connection' => 'sqlite']);
}

test('the mirror is inert unless enabled', function () {
    config(['analytics.mirror_enabled' => false]);

    Client::factory()->create();

    expect(DB::table('analytics_customers')->count())->toBe(0);
});

test('creating, updating and deleting a client keeps analytics_customers in sync', function () {
    enableMirror();

    $client = Client::factory()->create([
        'first_name' => 'Ana', 'discount' => 0, 'installment' => null,
        'price_cal_response' => json_encode(['price' => '349.00', 'discount_price' => '0']),
    ]);

    $row = DB::table('analytics_customers')->where('client_id', $client->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->first_name)->toBe('Ana')
        ->and((float) $row->total_contract_value)->toBe(349.0)
        ->and(DB::table('analytics_sales_events')->where('client_id', $client->id)->exists())->toBeTrue();

    $client->update(['first_name' => 'Bea']);
    expect(DB::table('analytics_customers')->where('client_id', $client->id)->value('first_name'))->toBe('Bea');

    $client->delete();
    expect(DB::table('analytics_customers')->where('client_id', $client->id)->exists())->toBeFalse()
        ->and(DB::table('analytics_sales_events')->where('client_id', $client->id)->exists())->toBeFalse();
});

test('payments, claims and leads mirror their own tables', function () {
    enableMirror();
    $client = Client::factory()->create();

    $payment = PaymentHistory::create(['client_id' => $client->id, 'amount' => 75, 'payment_status' => 1, 'claim_payment' => 0, 'refund_payment' => 0, 'is_custom' => 0]);
    $p = DB::table('analytics_payments')->where('payment_id', $payment->id)->first();
    expect($p)->not->toBeNull()->and((int) $p->is_success)->toBe(1)->and((float) $p->amount)->toBe(75.0);

    $claim = Claim::create(['client_id' => $client->id, 'claim_no' => 'CLM-1', 'status' => 1, 'total_price' => 500]);
    expect(DB::table('analytics_claims')->where('claim_id', $claim->id)->value('claim_number'))->toBe('CLM-1');

    $lead = LeadDetail::factory()->create(['first_name' => 'Lee', 'last_name' => 'Ad']);
    expect(DB::table('analytics_leads')->where('lead_id', $lead->id)->value('name'))->toBe('Lee Ad');
});

test('the row builders are pure and derive the contract value from price_cal_response', function () {
    $svc = app(AnalyticsSyncService::class);

    $installmentClient = Client::factory()->make([
        'installment' => 3, 'price_cal_response' => json_encode(['installment_price' => '1,200.00', 'price' => '2000']),
    ]);
    expect($svc->customerRow($installmentClient)['total_contract_value'])->toBe(1200.0);

    $discountClient = Client::factory()->make([
        'installment' => null, 'discount' => 25, 'price_cal_response' => json_encode(['discount_price' => '275.50', 'price' => '350']),
    ]);
    expect($svc->customerRow($discountClient)['total_contract_value'])->toBe(275.5);
});

test('the backfill command refuses when the mirror is off and populates it when on', function () {
    config(['analytics.mirror_enabled' => false]);
    $this->artisan('analytics:backfill')->assertFailed();

    enableMirror();
    $client = Client::factory()->create();
    // wipe what the observer wrote so the backfill is doing the work
    DB::table('analytics_customers')->delete();

    $this->artisan('analytics:backfill', ['--entity' => 'customers'])->assertSuccessful();

    expect(DB::table('analytics_customers')->where('client_id', $client->id)->exists())->toBeTrue();
});
