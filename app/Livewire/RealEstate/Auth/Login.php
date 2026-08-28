<?php

namespace App\Livewire\RealEstate\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Real-estate agent sign-in. Port of
 * RealEstate\AgentAuthController::login.
 */
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public function mount(): mixed
    {
        if (Auth::guard('agent')->check()) {
            return $this->redirectRoute('realesate.home', navigate: true);
        }

        return null;
    }

    public function login(): mixed
    {
        $this->validate();

        if (! Auth::guard('agent')->attempt(['email' => $this->email, 'password' => $this->password])) {
            $this->addError('email', __('These credentials do not match our records.'));

            return null;
        }

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        return $this->redirectRoute('realesate.home', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.realestate.auth.login')->layout('layouts.public', ['title' => __('Agent sign in')]);
    }
}
