<?php

use App\Livewire\Disputes\Index as DisputesIndex;
use App\Livewire\PaymentFailed\Board as FailedBoard;
use App\Livewire\PaymentHealth\Index as HealthIndex;
use App\Livewire\Renewals\Board as RenewalBoard;
use App\Models\Client;
use App\Models\ClientButtonTrack;
use App\Models\ClientRenewTrackAction;
use App\Models\ClientTrackAction;
use App\Models\PaymentHealthEvent;
use App\Models\PaymentHealthSnapshot;
use App\Models\PaymentHistory;
use App\Models\User;
use App\Services\Billing\DisputeService;
use App\Services\Billing\PaymentHealthReport;
use App\Services\Renewals\RenewalQueueService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->superAdmin()->create());
});

/* ---------- Payment Failed board ---------- */

test('the payment failed board walks the queue of failed customers', function () {
    Client::factory()->count(2)->create(['status' => 0]);
    Client::factory()->create(['status' => 1, 'first_name' => 'Fine']);

    Livewire::test(FailedBoard::class)
        ->assertSee('Customer 1 of 2')
        ->assertDontSee('Fine')
        ->call('next')
        ->assertSee('Customer 2 of 2');
});

test('a board note writes a button-track row and a note; an action row is logged', function () {
    $client = Client::factory()->create(['status' => 0]);

    Livewire::test(FailedBoard::class)
        ->set('noteBody', 'Left a voicemail about the card decline.')
        ->call('saveNote')
        ->call('logAction', 'send_sms');

    expect(ClientButtonTrack::where('client_id', $client->id)->exists())->toBeTrue()
        ->and(ClientTrackAction::where('client_id', $client->id)->whereNotNull('send_sms')->exists())->toBeTrue();
});

test('marking recovered flips the client back to active', function () {
    $client = Client::factory()->create(['status' => 0, 'payment_faild_date' => now()]);

    Livewire::test(FailedBoard::class)->call('markRecovered');

    expect($client->fresh()->status)->toBe(1)
        ->and($client->fresh()->payment_faild_date)->toBeNull();
});

/* ---------- Renewal board ---------- */

test('a renewal board disposition logs a track row and advances the queue', function () {
    Client::factory()->yearly()->create(['status' => 1, 'end_date' => now()->addDays(20)->toDateString(), 'first_name' => 'First']);
    Client::factory()->yearly()->create(['status' => 1, 'end_date' => now()->addDays(50)->toDateString(), 'first_name' => 'Second']);

    Livewire::test(RenewalBoard::class, ['filter' => 'yearly'])
        ->assertSee('First')
        ->call('disposition', 'spoke_with')
        ->assertSet('i', 1)
        ->assertSee('Second');

    expect(ClientRenewTrackAction::whereNotNull('save')->where('client_status', 'Spoke With')->exists())->toBeTrue();
});

test('marking a renewal customer renewed drops them from the queue', function () {
    $client = Client::factory()->yearly()->create(['status' => 1, 'end_date' => now()->addDays(15)->toDateString()]);

    Livewire::test(RenewalBoard::class, ['filter' => 'yearly'])
        ->call('disposition', 'renewed');

    expect((bool) $client->fresh()->renewed)->toBeTrue()
        ->and(app(RenewalQueueService::class)->ids('all'))->not->toContain($client->id);
});

/* ---------- Disputes ---------- */

test('a dispute can be opened, won, and cleared', function () {
    $client = Client::factory()->create();
    $payment = PaymentHistory::create(['client_id' => $client->id, 'amount' => 80, 'payment_status' => 1, 'transaction_id' => 'TXN']);

    $svc = app(DisputeService::class);

    $svc->markDisputed($payment, 'chargeback received');
    expect($payment->fresh()->disputed)->toBeTrue()
        ->and(PaymentHealthEvent::where('payment_history_id', $payment->id)->where('status', 'open')->exists())->toBeTrue();

    $svc->resolveDispute($payment->fresh(), 'win');
    expect($payment->fresh()->win)->toBeTrue()
        ->and(PaymentHealthEvent::where('payment_history_id', $payment->id)->where('status', 'resolved')->exists())->toBeTrue();

    $svc->resetDispute($payment->fresh());
    expect((bool) $payment->fresh()->disputed)->toBeFalse()
        ->and(PaymentHealthEvent::where('payment_history_id', $payment->id)->exists())->toBeFalse();
});

test('the disputes screen lists open disputes and resolves them', function () {
    $client = Client::factory()->create(['first_name' => 'Deb', 'last_name' => 'Dispute']);
    $payment = PaymentHistory::create(['client_id' => $client->id, 'amount' => 50, 'payment_status' => 1, 'disputed' => 1]);

    Livewire::test(DisputesIndex::class)
        ->assertSee('Deb Dispute')
        ->call('resolve', $payment->id, 'loss');

    expect($payment->fresh()->loss)->toBeTrue();
});

/* ---------- Payment Health ---------- */

test('the monthly roll-up counts paid, failed, refunds and disputes', function () {
    $client = Client::factory()->create();
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 100, 'payment_status' => 1, 'claim_payment' => 0]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 100, 'payment_status' => 0, 'is_custom' => 0, 'claim_payment' => 0]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 100, 'payment_status' => 1, 'refund_payment' => 1, 'refund_amount' => 40, 'claim_payment' => 0]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 100, 'payment_status' => 1, 'disputed' => 1, 'loss' => 1, 'claim_payment' => 0]);

    $metrics = app(PaymentHealthReport::class)->forMonth(Carbon::now());

    expect($metrics['paid_count'])->toBe(3)
        ->and($metrics['failed_count'])->toBe(1)
        ->and($metrics['refund_count'])->toBe(1)
        ->and($metrics['dispute_count'])->toBe(1)
        ->and((float) $metrics['net_amount'])->toBe(300.0 - 40.0 - 100.0); // gross - refund - lost dispute
});

test('saving a snapshot persists one row per month', function () {
    Client::factory()->create();

    Livewire::test(HealthIndex::class)
        ->set('month', now()->format('Y-m'))
        ->call('saveSnapshot')
        ->call('saveSnapshot');

    expect(PaymentHealthSnapshot::count())->toBe(1);
});

test('Phase 7 pages are permission gated', function () {
    $this->actingAs(User::factory()->withRole(['Customers'])->create());

    $this->get(route('payment-failed.index'))->assertForbidden();
    $this->get(route('renewals.index'))->assertForbidden();
    $this->get(route('payment-health.index'))->assertForbidden();
});
