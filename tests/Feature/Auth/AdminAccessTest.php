<?php

use App\Models\User;

test('non-admin accounts (type != 1) are blocked from the panel', function () {
    $this->actingAs(User::factory()->nonAdmin()->create());

    $this->get(route('dashboard'))->assertForbidden();
});

test('a disabled admin (login_off) is blocked', function () {
    $this->actingAs(User::factory()->create(['login_off' => true]));

    $this->get(route('dashboard'))->assertForbidden();
});

test('superadmin (role_id null) passes every permission gate', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('customers.index'))->assertOk();
    $this->get(route('renewals.index'))->assertOk();
});

test('a scoped role only reaches routes its permissions allow', function () {
    $user = User::factory()->withRole(['Customers'])->create();

    $this->actingAs($user);

    $this->get(route('customers.index'))->assertOk();
    $this->get(route('renewals.index'))->assertForbidden();
});

test('hasPermission reflects the role permission list', function () {
    $user = User::factory()->withRole(['Customers', 'Sale Sheet'])->create();

    expect($user->hasPermission('Customers'))->toBeTrue()
        ->and($user->hasPermission('Sale Sheet'))->toBeTrue()
        ->and($user->hasPermission('Renewal(VB)'))->toBeFalse();
});
