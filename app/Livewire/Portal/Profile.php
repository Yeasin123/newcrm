<?php

namespace App\Livewire\Portal;

use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Customer-portal profile + password. Port of Clint\SettingController's
 * updateProfile / changePassword (the `unique:users` bug in the legacy
 * validator is corrected to `unique:clients`; profile-photo upload is
 * dropped for now).
 */
class Profile extends Component
{
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $contact = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $zip_code = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $client = auth('user')->user();

        foreach (['first_name', 'last_name', 'email', 'contact', 'address', 'city', 'state', 'zip_code'] as $field) {
            $this->{$field} = (string) ($client->{$field} ?? '');
        }
    }

    public function saveProfile(): void
    {
        /** @var Client $client */
        $client = auth('user')->user();

        $data = $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('clients', 'email')->ignore($client->id)],
            'contact' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:120',
            'zip_code' => 'nullable|string|max:20',
        ], attributes: ['email' => __('email address')]);

        $client->forceFill($data)->save();

        session()->flash('success', __('Your profile has been updated.'));
    }

    public function changePassword(): void
    {
        /** @var Client $client */
        $client = auth('user')->user();

        $this->validate([
            'current_password' => 'nullable|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (filled($this->current_password) && $client->password
            && ! Hash::check($this->current_password, $client->password)) {
            $this->addError('current_password', __('Your current password does not match.'));

            return;
        }

        $client->forceFill(['password' => Hash::make($this->password)])->save();
        auth('user')->setUser($client->fresh());

        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('success', __('Your password has been updated.'));
    }

    public function render(): View
    {
        return view('livewire.portal.profile')->layout('layouts.public', ['title' => __('Profile')]);
    }
}
