<?php

use App\Livewire\Earnings\Index;
use App\Models\Claim;
use App\Models\Client;
use App\Models\ClientEarning;
use App\Models\PaymentHistory;
use App\Models\User;
use App\Services\Earnings\ClientEarningsCalculator;
use App\Services\Earnings\EarningsAnalyticsService;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

test('the client-earnings calculator rolls up collected and profit per client', function () {
    $client = Client::factory()->create();

    PaymentHistory::create(['client_id' => $client->id, 'amount' => 100, 'payment_status' => 1, 'claim_payment' => 0]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 100, 'payment_status' => 1, 'claim_payment' => 0, 'refund_amount' => 25]);
    Claim::create(['client_id' => $client->id, 'total_price' => 40, 'claim_no' => 'C1']);

    app(ClientEarningsCalculator::class)->recalculateFor($client->id);

    $row = ClientEarning::where('client_id', $client->id)->first();

    expect((float) $row->total_earn)->toBe(175.0)
        ->and((float) $row->total_profit)->toBe(135.0);
});

test('mrrFor resolves monthly, installment and yearly plans to a monthly-equivalent', function () {
    $svc = app(EarningsAnalyticsService::class);

    $monthly = (object) ['payment_structure' => '1', 'installment' => null, 'start_date' => null, 'end_date' => null,
        'price_cal_response' => json_encode(['price' => '54.99'])];
    $inst = (object) ['payment_structure' => '2', 'installment' => 4, 'start_date' => null, 'end_date' => null,
        'price_cal_response' => json_encode(['installment_price' => '120', 'price' => '480'])];
    $yearly = (object) ['payment_structure' => '2', 'installment' => null,
        'start_date' => '2026-01-01', 'end_date' => '2027-01-01',
        'price_cal_response' => json_encode(['price' => '1200'])];

    expect($svc->mrrFor($monthly))->toBe(54.99)
        ->and($svc->mrrFor($inst))->toBe(120.0)
        ->and($svc->mrrFor($yearly))->toBe(100.0); // 1200 / 12 months
});

test('the earnings overview sums MRR across active customers', function () {
    Client::factory()->create(['status' => 1, 'login_off' => false, 'payment_structure' => '1', 'price_cal_response' => json_encode(['price' => '50'])]);
    Client::factory()->create(['status' => 1, 'login_off' => false, 'payment_structure' => '1', 'price_cal_response' => json_encode(['price' => '30'])]);
    Client::factory()->create(['status' => 0, 'payment_structure' => '1', 'price_cal_response' => json_encode(['price' => '999'])]); // cancelled, excluded

    Livewire::test(Index::class)
        ->assertSee('Gross MRR')
        ->assertSee('$80.00')
        ->assertSee('$960.00'); // ARR = 80 x 12
});

test('the earnings tabs each render', function () {
    Client::factory()->count(2)->create(['status' => 1, 'payment_structure' => '1', 'price_cal_response' => json_encode(['price' => '40'])]);

    Livewire::test(Index::class)
        ->call('setTab', 'revenue')->assertSee('Revenue by Plan Type')
        ->call('setTab', 'commissions')->assertSee('Commission by Salesman')
        ->call('setTab', 'salesman')->assertSee('Salesman Earnings Breakdown');
});

test('the Earnings permission gates the page', function () {
    $this->actingAs(User::factory()->withRole(['Search'])->create());

    $this->get(route('earnings.index'))->assertForbidden();
});
