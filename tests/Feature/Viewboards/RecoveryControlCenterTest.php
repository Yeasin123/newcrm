<?php

use App\Livewire\PaymentFailed\Index as PaymentFailedIndex;
use App\Livewire\Renewals\Board;
use App\Livewire\Renewals\Index as RenewalsIndex;
use App\Models\Client;
use App\Models\ClientButtonTrack;
use App\Models\ClientRenewButtonTrack;
use App\Models\FailedPaymentSetting;
use App\Models\PaymentHistory;
use App\Models\User;
use App\Services\FailedPayment\RecoveryControlCenterSettings;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->superAdmin()->create());
});

/* -------------------------------------------------- Renewal Queue */

test('the renewal queue lists yearly policies inside the ~4 month window', function () {
    $due = Client::factory()->yearly()->create([
        'status' => 1, 'first_name' => 'Rhonda', 'last_name' => 'Renew',
        'end_date' => now()->addDays(30)->toDateString(), 'custom_price' => 606.65,
    ]);
    Client::factory()->yearly()->create([
        'status' => 1, 'first_name' => 'Far', 'last_name' => 'Future',
        'end_date' => now()->addYear()->toDateString(),
    ]);

    Livewire::test(RenewalsIndex::class)
        ->assertSee('Renewal Queue')
        ->assertSee('Rhonda Renew')
        ->assertDontSee('Far Future');
});

test('marking a renewal customer contacted writes a client_renew_button_tracks row', function () {
    $client = Client::factory()->yearly()->create([
        'status' => 1, 'end_date' => now()->addDays(20)->toDateString(),
    ]);

    Livewire::test(RenewalsIndex::class)->call('markContacted', $client->id);

    expect(ClientRenewButtonTrack::where('client_id', $client->id)->where('button_text', 'Contacted')->exists())->toBeTrue();
});

test('the renewal board deep-links to a specific customer in the queue', function () {
    Client::factory()->yearly()->create(['status' => 1, 'end_date' => now()->addDays(20)->toDateString()]);
    $target = Client::factory()->yearly()->create([
        'status' => 1, 'first_name' => 'Deep', 'last_name' => 'Link',
        'end_date' => now()->addDays(60)->toDateString(),
    ]);

    Livewire::test(Board::class, ['client' => $target->id, 'filter' => 'yearly'])
        ->assertSet('i', 1)
        ->assertSee('Deep Link');
});

/* -------------------------------------------------- Recovery control center */

test('the recovery control center renders the overview and the failed queue', function () {
    Client::factory()->count(3)->create(['status' => 0, 'payment_faild_date' => now()]);
    Client::factory()->create(['status' => 1, 'first_name' => 'Healthy']);

    Livewire::test(PaymentFailedIndex::class)
        ->assertSee('Recovery control center')
        ->assertSee('Active failures')
        ->set('tab', 'queue')
        ->assertSee('Failed payments')
        ->assertDontSee('Healthy');
});

test('saving the control center persists the config and mirrors the cron schedule', function () {
    Livewire::test(PaymentFailedIndex::class)
        ->set('cfg.hourly_attempt_limit', 25)
        ->set('cfg.charge_schedule.0.day', 2)
        ->set('cfg.charge_timezone', 'America/Chicago')
        ->call('save')
        ->assertHasNoErrors();

    $stored = app(RecoveryControlCenterSettings::class)->load();
    expect($stored['hourly_attempt_limit'])->toBe(25)
        ->and($stored['charge_schedule'][0]['day'])->toBe(2);

    $mirror = FailedPaymentSetting::where('setting_key', 'retry_timezone')->first();
    expect($mirror->setting_value['value'])->toBe('America/Chicago');
});

test('live mode is refused until the billing gate is live', function () {
    $result = app(RecoveryControlCenterSettings::class)->save(['operating_mode' => 'live']);

    expect($result['operating_mode'])->toBe('paused');
});

test('a test-mode retry never charges or flips the customer active', function () {
    $client = Client::factory()->create(['status' => 0, 'payment_faild_date' => now()]);

    Livewire::test(PaymentFailedIndex::class)->call('retry', $client->id);

    expect($client->fresh()->status)->toBe(0)
        ->and(PaymentHistory::where('client_id', $client->id)->exists())->toBeFalse();
});

test('marking a queue row contacted logs a board track', function () {
    $client = Client::factory()->create(['status' => 0]);

    Livewire::test(PaymentFailedIndex::class)->call('markContacted', $client->id);

    expect(ClientButtonTrack::where('client_id', $client->id)->where('button_text', 'Contacted')->exists())->toBeTrue();
});

test('the control center is permission gated', function () {
    $this->actingAs(User::factory()->withRole(['Customers'])->create());

    $this->get(route('payment-failed.index'))->assertForbidden();
    $this->get(route('renewals.index'))->assertForbidden();
});
