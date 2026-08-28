<?php

namespace App\Livewire\Portal\Auth;

use App\Models\Client;
use App\Services\Portal\PortalLoginService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Customer-portal sign-in. Replaces the legacy Web\LoginController::index +
 * login + forgetPasswordSubmit. A signed-in customer is bounced to the
 * portal home.
 */
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public string $lookupPolicy = '';

    public ?string $lookupResult = null;

    public function mount(): mixed
    {
        if (auth('user')->check()) {
            return $this->redirectRoute(config('portal.home_route'), navigate: true);
        }

        return null;
    }

    public function login(PortalLoginService $auth): mixed
    {
        $this->validate();

        try {
            $auth->attempt($this->email, $this->password, request()->ip());
        } catch (ValidationException $e) {
            throw $e;
        }

        return $this->redirectRoute(config('portal.home_route'), navigate: true);
    }

    /**
     * Legacy "forgot password" helper — it never sent a reset, it just
     * confirmed the policy existed and echoed the email on file.
     */
    public function findPolicy(): void
    {
        $this->validate(['lookupPolicy' => 'required|string']);

        $formatted = str_contains($this->lookupPolicy, '-')
            ? $this->lookupPolicy
            : rtrim(implode('-', str_split($this->lookupPolicy, 3)), '-');

        $client = Client::where('policy_number', $formatted)->first();

        if (! $client) {
            $this->addError('lookupPolicy', __('No policy found with that number.'));
            $this->lookupResult = null;

            return;
        }

        $masked = preg_replace('/(?<=.).(?=.*@)/', '*', (string) $client->email);
        $this->lookupResult = __('Your account email is :email. Sign in with your policy number.', ['email' => $masked]);
    }

    public function render(): View
    {
        return view('livewire.portal.auth.login')->layout('layouts.public', ['title' => __('Customer sign in')]);
    }
}
