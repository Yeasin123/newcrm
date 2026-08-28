<?php

use App\Livewire\Portal\PackageDetails;
use App\Livewire\Portal\Profile;
use App\Models\Addon;
use App\Models\Client;
use App\Models\Package;
use App\Models\PaymentHistory;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('the dashboard shows the customer their own plan and payments only', function () {
    $package = Package::create(['title' => 'Total Home', 'monthly' => '60', 'yearly' => '600']);
    $me = Client::factory()->create([
        'package' => $package->id, 'payment_structure' => '1', 'status' => 1,
        'price_cal_response' => json_encode(['price' => '60.00', 'discount_price' => '0.00']),
    ]);
    $other = Client::factory()->create();

    PaymentHistory::create(['client_id' => $me->id, 'amount' => 60, 'purpose' => 'Total Home (monthly)', 'payment_date' => now()->subDay(), 'payment_status' => 1]);
    PaymentHistory::create(['client_id' => $other->id, 'amount' => 999, 'purpose' => 'Someone else', 'payment_date' => now(), 'payment_status' => 1]);

    $this->actingAs($me, 'user')
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Total Home')
        ->assertSee('Total Home (monthly)')
        ->assertDontSee('Someone else');
});

test('the plan page renders the price breakdown, add-ons and history', function () {
    $package = Package::create(['title' => 'Total Home', 'monthly' => '60', 'yearly' => '600']);
    $addon = Addon::create(['title' => 'Pool coverage', 'monthly' => '12', 'yearly' => '120']);
    $client = Client::factory()->create([
        'package' => $package->id, 'payment_structure' => '1',
        'services' => json_encode([$addon->id]),
        'discount' => '10',
        'price_cal_response' => json_encode(['price' => '72.00', 'discount_price' => '62.00']),
        'start_date' => now()->toDateString(),
    ]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 62, 'purpose' => 'Total Home (monthly)', 'payment_date' => now(), 'payment_status' => 1]);

    Livewire::actingAs($client, 'user')
        ->test(PackageDetails::class)
        ->assertSee('Total Home')
        ->assertSee('Pool coverage')
        ->assertSee('72.00')
        ->assertSee('62.00');
});

test('the customer can update their profile', function () {
    $client = Client::factory()->create(['first_name' => 'Old', 'email' => 'me@example.com']);
    Client::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($client, 'user')
        ->test(Profile::class)
        ->set('first_name', 'New')
        ->set('email', 'me@example.com')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($client->fresh()->first_name)->toBe('New');

    Livewire::actingAs($client, 'user')
        ->test(Profile::class)
        ->set('email', 'taken@example.com')
        ->call('saveProfile')
        ->assertHasErrors('email');
});

test('the customer can change their password with the right current password', function () {
    $client = Client::factory()->create(['password' => Hash::make('old-password')]);

    Livewire::actingAs($client, 'user')
        ->test(Profile::class)
        ->set('current_password', 'wrong')
        ->set('password', 'brand-new-pw')
        ->set('password_confirmation', 'brand-new-pw')
        ->call('changePassword')
        ->assertHasErrors('current_password');

    Livewire::actingAs($client, 'user')
        ->test(Profile::class)
        ->set('current_password', 'old-password')
        ->set('password', 'brand-new-pw')
        ->set('password_confirmation', 'brand-new-pw')
        ->call('changePassword')
        ->assertHasNoErrors();

    expect(Hash::check('brand-new-pw', $client->fresh()->password))->toBeTrue();
});

test('a short or unconfirmed new password is rejected', function () {
    $client = Client::factory()->create(['password' => Hash::make('old-password')]);

    Livewire::actingAs($client, 'user')
        ->test(Profile::class)
        ->set('password', 'short')
        ->set('password_confirmation', 'nope')
        ->call('changePassword')
        ->assertHasErrors('password');
});
