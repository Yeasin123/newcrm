<?php

use App\Livewire\Billing\AuthorizeAccounts;
use App\Models\AuthorizeAccount;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

test('a merchant account can be created', function () {
    Livewire::test(AuthorizeAccounts::class)
        ->call('newAccount')
        ->set('name', 'Primary')
        ->set('environment', 'production')
        ->set('api_login_id', 'login-abc')
        ->set('transaction_key', 'key-abc')
        ->set('signature_key', 'sig-abc')
        ->call('save')
        ->assertHasNoErrors();

    $acc = AuthorizeAccount::first();
    expect($acc->name)->toBe('Primary')
        ->and($acc->api_login_id)->toBe('login-abc')   // encrypted cast round-trips
        ->and($acc->signature_key)->toBe('sig-abc');
});

test('editing with blank credentials keeps the existing ones', function () {
    $acc = AuthorizeAccount::create([
        'name' => 'Keep', 'environment' => 'sandbox',
        'api_login_id' => 'orig-login', 'transaction_key' => 'orig-key', 'signature_key' => 'orig-sig',
    ]);

    Livewire::test(AuthorizeAccounts::class)
        ->call('edit', $acc->id)
        ->set('name', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    $acc->refresh();
    expect($acc->name)->toBe('Renamed')
        ->and($acc->api_login_id)->toBe('orig-login')
        ->and($acc->signature_key)->toBe('orig-sig');
});

test('only one account can be the default for charges', function () {
    $a = AuthorizeAccount::create(['name' => 'A', 'environment' => 'sandbox', 'api_login_id' => 'x', 'transaction_key' => 'y', 'is_default_charge' => true]);
    $b = AuthorizeAccount::create(['name' => 'B', 'environment' => 'sandbox', 'api_login_id' => 'x', 'transaction_key' => 'y']);

    Livewire::test(AuthorizeAccounts::class)
        ->call('edit', $b->id)
        ->set('is_default_charge', true)
        ->call('save');

    expect($a->fresh()->is_default_charge)->toBeFalse()
        ->and($b->fresh()->is_default_charge)->toBeTrue();
});

test('the page requires the Settings permission', function () {
    $this->actingAs(User::factory()->withRole(['Customers'])->create());

    $this->get(route('billing.accounts'))->assertForbidden();
});
