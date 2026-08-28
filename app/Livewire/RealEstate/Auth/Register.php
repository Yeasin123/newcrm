<?php

namespace App\Livewire\RealEstate\Auth;

use App\Models\RealEstate\Company;
use App\Services\RealEstate\AgentRegistrationService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Agent sign-up wizard. Port of RealEstate\AgentAuthController::storeData
 * (step 1 — account) + register (step 2 — office details). Company is
 * chosen from the existing list or created from a typed name.
 */
class Register extends Component
{
    public int $step = 1;

    // Step 1 — account
    public string $email = '';

    public string $role = '';

    public ?int $company_id = null;

    public string $company_name = '';

    public string $password = '';

    public string $confirmPassword = '';

    // Step 2 — office
    public string $name = '';

    public string $address_one = '';

    public string $address_two = '';

    public string $city = '';

    public string $state = '';

    public string $postal_code = '';

    public string $second_email = '';

    public string $office_number = '';

    public string $mobile_number = '';

    #[Computed]
    public function companies()
    {
        return Company::orderByDesc('id')->get(['id', 'name']);
    }

    public function stepOne(): void
    {
        $this->validate([
            'email' => 'required|email',
            'role' => 'required',
            'password' => 'required|string|min:6',
            'confirmPassword' => 'required|same:password',
            'company_id' => 'nullable|integer',
            'company_name' => 'required_without:company_id|nullable|string',
        ]);

        $this->step = 2;
    }

    public function register(AgentRegistrationService $registration): mixed
    {
        $office = $this->validate([
            'name' => 'required|string',
            'address_one' => 'required|string',
            'city' => 'required|string',
            'office_number' => 'required|string',
            'address_two' => 'nullable|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'second_email' => 'nullable|email',
            'mobile_number' => 'nullable|string',
        ]);

        $company = $registration->resolveCompany($this->company_id, $this->company_name);

        $registration->register(
            ['email' => $this->email, 'role' => $this->role, 'password' => $this->password],
            $office,
            $company,
        );

        return $this->redirectRoute('realesate.home', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.realestate.auth.register')->layout('layouts.public', ['title' => __('Agent sign up')]);
    }
}
