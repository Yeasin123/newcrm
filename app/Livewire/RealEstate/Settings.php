<?php

namespace App\Livewire\RealEstate;

use App\Models\RealEstate\Agent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Agent profile + password. Port of RealEstate\SettingsController::settings
 * / settingsUpdate / updatePassword (image upload dropped for now).
 */
class Settings extends Component
{
    public string $name = '';

    public string $second_email = '';

    public string $address_one = '';

    public string $address_two = '';

    public string $city = '';

    public string $state = '';

    public string $postal_code = '';

    public string $office_number = '';

    public string $mobile_number = '';

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $agent = Auth::guard('agent')->user();

        foreach (['name', 'second_email', 'address_one', 'address_two', 'city', 'state', 'postal_code', 'office_number', 'mobile_number'] as $field) {
            $this->{$field} = (string) ($agent->{$field} ?? '');
        }
    }

    public function saveProfile(): void
    {
        $data = $this->validate([
            'name' => 'required|string',
            'second_email' => 'nullable|email',
            'address_one' => 'nullable|string',
            'address_two' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'office_number' => 'nullable|string',
            'mobile_number' => 'nullable|string',
        ]);

        Auth::guard('agent')->user()->update($data);

        session()->flash('success', __('Your profile has been updated.'));
    }

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        /** @var Agent $agent */
        $agent = Auth::guard('agent')->user();

        if (! Hash::check($this->current_password, $agent->password)) {
            $this->addError('current_password', __('Current password does not match.'));

            return;
        }

        $agent->forceFill(['password' => Hash::make($this->new_password)])->save();

        $this->reset('current_password', 'new_password', 'new_password_confirmation');
        session()->flash('success', __('Your password has been updated.'));
    }

    public function render(): View
    {
        return view('livewire.realestate.settings')->layout('layouts.public', ['title' => __('Agent settings')]);
    }
}
