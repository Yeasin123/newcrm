<?php

use App\Models\User;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->first_name)->toBe('Test')
        ->and($user->last_name)->toBe('User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->name)->toBe('Test User');
});

test('first name is required', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::settings.profile')
        ->set('first_name', '')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation')
        ->assertHasErrors('first_name');
});
