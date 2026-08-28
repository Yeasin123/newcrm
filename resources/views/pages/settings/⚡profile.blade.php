<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use ProfileValidationRules;

    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->first_name = (string) $user->first_name;
        $this->last_name = (string) $user->last_name;
        $this->email = (string) $user->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);
        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="first_name" :label="__('First name')" type="text" required autofocus autocomplete="given-name" />
                <flux:input wire:model="last_name" :label="__('Last name')" type="text" autocomplete="family-name" />
            </div>

            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
