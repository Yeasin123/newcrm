<?php

use App\Livewire\Portal\Auth\Login;
use App\Models\Client;
use App\Models\ClientLoginInfo;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('the sign-in page renders for guests', function () {
    $this->get('/user/login')->assertOk()->assertSee('Customer sign in');
});

test('a customer signs in with a bcrypt password and is audited', function () {
    $client = Client::factory()->create([
        'email' => 'Cust@Example.com ', 'password' => Hash::make('secret-pass'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'cust@example.com')
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertRedirect(route('portal.claims.index'));

    expect(auth('user')->id())->toBe($client->id)
        ->and($client->fresh()->last_login_at)->not->toBeNull()
        ->and(ClientLoginInfo::where('client_id', $client->id)->count())->toBe(1);
});

test('a wrong password is rejected', function () {
    Client::factory()->create(['email' => 'cust@example.com', 'password' => Hash::make('secret-pass')]);

    Livewire::test(Login::class)
        ->set('email', 'cust@example.com')->set('password', 'nope')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth('user')->check())->toBeFalse();
});

test('a cancelled policy cannot sign in', function () {
    Client::factory()->create([
        'email' => 'cust@example.com', 'password' => Hash::make('secret-pass'), 'login_off' => true,
    ]);

    Livewire::test(Login::class)
        ->set('email', 'cust@example.com')->set('password', 'secret-pass')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth('user')->check())->toBeFalse();
});

test('legacy fallbacks are off by default: plaintext and policy number do not work', function () {
    config()->set('portal.legacy_login_fallbacks', false);
    Client::factory()->create([
        'email' => 'cust@example.com', 'password' => 'plain-text-pw', 'policy_number' => '123-456-789',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'cust@example.com')->set('password', 'plain-text-pw')
        ->call('login')->assertHasErrors('email');

    Livewire::test(Login::class)
        ->set('email', 'cust@example.com')->set('password', '123456789')
        ->call('login')->assertHasErrors('email');

    expect(auth('user')->check())->toBeFalse();
});

test('with the flag on, a plaintext password works once and is re-hashed', function () {
    config()->set('portal.legacy_login_fallbacks', true);
    $client = Client::factory()->create([
        'email' => 'cust@example.com', 'password' => 'plain-text-pw',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'cust@example.com')->set('password', 'plain-text-pw')
        ->call('login')
        ->assertRedirect(route('portal.claims.index'));

    expect(Hash::check('plain-text-pw', $client->fresh()->password))->toBeTrue();
});

test('with the flag on, the policy number as password works', function () {
    config()->set('portal.legacy_login_fallbacks', true);
    $client = Client::factory()->create([
        'email' => 'cust@example.com', 'password' => Hash::make('unknown'), 'policy_number' => '123-456-789',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'cust@example.com')->set('password', '123456789')
        ->call('login')
        ->assertRedirect(route('portal.claims.index'));

    expect(auth('user')->id())->toBe($client->id);
});

test('the portal is gated and redirects guests to sign in', function () {
    $this->get(route('portal.claims.index'))->assertRedirect(route('login.user'));
});

test('a signed-in customer reaches the portal home', function () {
    $client = Client::factory()->create();

    $this->actingAs($client, 'user')
        ->get(route('portal.claims.index'))
        ->assertOk()
        ->assertSee('File a claim');
});

test('the forgot-password helper confirms a policy exists', function () {
    Client::factory()->create(['policy_number' => '111-222-333', 'email' => 'find@example.com']);

    Livewire::test(Login::class)
        ->set('lookupPolicy', '111222333')
        ->call('findPolicy')
        ->assertSet('lookupResult', fn ($v) => str_contains($v, '@example.com'));

    Livewire::test(Login::class)
        ->set('lookupPolicy', '999999999')
        ->call('findPolicy')
        ->assertHasErrors('lookupPolicy');
});
