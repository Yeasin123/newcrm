<?php

use App\Livewire\Disputes\Index as DisputesIndex;
use App\Livewire\Financials\Index as FinancialsIndex;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

/* -------------------------------------------------- Financials */

test('financials computes net P&L from payment history', function () {
    $client = Client::factory()->create();
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 500, 'payment_status' => 1, 'claim_payment' => 0]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 120, 'payment_status' => 1, 'disputed' => 1, 'claim_payment' => 0]);

    Livewire::test(FinancialsIndex::class)
        ->assertSee('Financials')
        ->assertSee('$620.00')   // gross — both successful charges
        ->assertSee('$120.00')   // chargebacks — the disputed charge
        ->assertSee('$500.00');  // net = 620 - 120
});

test('financial statement tabs show a connect prompt', function () {
    Livewire::test(FinancialsIndex::class)
        ->set('tab', 'payouts')
        ->assertSee('No payouts for this period')
        ->set('tab', 'reserves')
        ->assertSee('No reserves for this period');
});

/* -------------------------------------------------- Disputes */

test('disputes shows the zen webhook prompt while zen is not connected', function () {
    Livewire::test(DisputesIndex::class)
        ->assertSee('Add this webhook URL to both Zen Payments accounts')
        ->assertSee('/api/zen/webhook');
});

test('sync from zen is a safe no-op when not connected', function () {
    Livewire::test(DisputesIndex::class)
        ->call('syncFromZen')
        ->assertHasNoErrors();
});

test('the disputed customers tab lists disputed payments and resolves them', function () {
    $client = Client::factory()->create(['first_name' => 'Deb', 'last_name' => 'Dispute']);
    $payment = PaymentHistory::create(['client_id' => $client->id, 'amount' => 90, 'payment_status' => 1, 'disputed' => 1]);

    Livewire::test(DisputesIndex::class)
        ->set('tab', 'customers')
        ->assertSee('Deb Dispute')
        ->call('resolve', $payment->id, 'win');

    expect($payment->fresh()->win)->toBeTrue();
});

test('the zen records tab shows the connect prompt', function () {
    Livewire::test(DisputesIndex::class)
        ->set('tab', 'zen')
        ->assertSee('Zen Payments is not connected');
});
