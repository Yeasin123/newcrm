<?php

namespace App\Services\RealEstate;

use App\Models\RealEstate\Agent;
use App\Models\RealEstate\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Agent sign-up. Port of RealEstate\AgentAuthController::storeData +
 * register — the legacy two-request flow (account creds, then office
 * details) is kept as two method calls here.
 */
class AgentRegistrationService
{
    /**
     * Resolve the brokerage: an existing id, or a new company created from a
     * typed name (legacy `/agent/store-company`).
     */
    public function resolveCompany(int|string|null $companyId, ?string $newName): Company
    {
        if ($companyId && $company = Company::find($companyId)) {
            return $company;
        }

        return Company::create([
            'name' => trim((string) $newName) ?: 'Real Estate',
            'image' => 'default.png',
            'type' => 'Real Estate',
            'status' => 1,
        ]);
    }

    /**
     * @param  array{email:string,role:mixed,password:string}  $account
     * @param  array{name:string,address_one:string,address_two?:?string,city:string,state?:?string,postal_code?:?string,second_email?:?string,office_number:string,mobile_number?:?string}  $office
     */
    public function register(array $account, array $office, Company $company): Agent
    {
        $agent = Agent::create([
            ...$office,
            'email' => $account['email'],
            'role' => $account['role'],
            'company_id' => $company->id,
            'password' => Hash::make($account['password']),
        ]);

        Auth::guard('agent')->login($agent);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        return $agent;
    }
}
