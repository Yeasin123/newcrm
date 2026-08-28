<?php

use App\Livewire\RealEstate\Auth\Login;
use App\Livewire\RealEstate\Auth\Register;
use App\Livewire\RealEstate\Settings;
use App\Models\RealEstate\Agent;
use App\Models\RealEstate\Company;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('the agent login and register pages render for guests', function () {
    $this->get(route('agent.login'))->assertOk()->assertSee('Agent sign in');
    $this->get(route('agent.register'))->assertOk()->assertSee('Agent sign up');
});

test('an agent signs in with valid credentials', function () {
    $company = Company::create(['name' => 'Acme Realty', 'type' => 'Real Estate']);
    $agent = Agent::create([
        'company_id' => $company->id, 'name' => 'Ada', 'email' => 'ada@acme.com',
        'password' => Hash::make('secret123'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'ada@acme.com')->set('password', 'secret123')
        ->call('login')
        ->assertRedirect(route('realesate.home'));

    expect(auth('agent')->id())->toBe($agent->id);
});

test('bad agent credentials are rejected', function () {
    Company::create(['name' => 'Acme Realty', 'type' => 'Real Estate']);
    Agent::create(['company_id' => 1, 'email' => 'ada@acme.com', 'password' => Hash::make('secret123')]);

    Livewire::test(Login::class)
        ->set('email', 'ada@acme.com')->set('password', 'wrong')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth('agent')->check())->toBeFalse();
});

test('registration is a two-step flow that creates the agent and signs them in', function () {
    $company = Company::create(['name' => 'Existing Co', 'type' => 'Real Estate']);

    Livewire::test(Register::class)
        ->set('email', 'new@agent.com')
        ->set('role', 'buyer')
        ->set('company_id', $company->id)
        ->set('password', 'password1')
        ->set('confirmPassword', 'password1')
        ->call('stepOne')
        ->assertSet('step', 2)
        ->set('name', 'Grace Hopper')
        ->set('address_one', '1 Navy Yard')
        ->set('city', 'DC')
        ->set('office_number', '2025550100')
        ->call('register')
        ->assertRedirect(route('realesate.home'));

    $agent = Agent::where('email', 'new@agent.com')->first();
    expect($agent)->not->toBeNull()
        ->and($agent->company_id)->toBe($company->id)
        ->and(Hash::check('password1', $agent->password))->toBeTrue()
        ->and(auth('agent')->id())->toBe($agent->id);
});

test('registration can create a brand-new company from a typed name', function () {
    Livewire::test(Register::class)
        ->set('email', 'solo@agent.com')->set('role', 'seller')
        ->set('company_name', 'Fresh Brokerage')
        ->set('password', 'password1')->set('confirmPassword', 'password1')
        ->call('stepOne')
        ->set('name', 'Solo')->set('address_one', '5 Main')->set('city', 'NYC')->set('office_number', '2125550100')
        ->call('register');

    expect(Company::where('name', 'Fresh Brokerage')->exists())->toBeTrue();
});

test('the agent area is gated', function () {
    $this->get(route('realesate.home'))->assertRedirect(route('agent.login'));
    $this->get(route('realestate.settings'))->assertRedirect(route('agent.login'));
});

test('an agent can update their profile and password', function () {
    $company = Company::create(['name' => 'Acme Realty', 'type' => 'Real Estate']);
    $agent = Agent::create([
        'company_id' => $company->id, 'name' => 'Ada', 'email' => 'ada@acme.com',
        'password' => Hash::make('old-password'),
    ]);

    Livewire::actingAs($agent, 'agent')->test(Settings::class)
        ->set('name', 'Ada Lovelace')->set('city', 'London')
        ->call('saveProfile')
        ->assertHasNoErrors();
    expect($agent->fresh()->name)->toBe('Ada Lovelace');

    Livewire::actingAs($agent, 'agent')->test(Settings::class)
        ->set('current_password', 'nope')
        ->set('new_password', 'new-password')->set('new_password_confirmation', 'new-password')
        ->call('changePassword')
        ->assertHasErrors('current_password');

    Livewire::actingAs($agent, 'agent')->test(Settings::class)
        ->set('current_password', 'old-password')
        ->set('new_password', 'new-password')->set('new_password_confirmation', 'new-password')
        ->call('changePassword')
        ->assertHasNoErrors();
    expect(Hash::check('new-password', $agent->fresh()->password))->toBeTrue();
});
