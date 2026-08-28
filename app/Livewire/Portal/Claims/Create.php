<?php

namespace App\Livewire\Portal\Claims;

use App\Models\Brand;
use App\Models\Client;
use App\Models\Issue;
use App\Models\System;
use App\Services\Billing\PaymentInput;
use App\Services\Portal\PortalClaimService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * "File a claim" — port of Clint\ClimController::claimStore. The eligibility
 * gate runs on mount (so an ineligible customer sees why up front) and again
 * inside PortalClaimService::fileClaim. Filing charges the service-call fee.
 */
class Create extends Component
{
    public ?string $gateError = null;

    public string $system = '';

    public string $issue = '';

    public string $brand = '';

    public string $problem = '';

    public string $last_time_working_detail = '';

    public string $serial_no = '';

    public string $location = '';

    public string $model_no = '';

    public string $expiry = '';

    public string $opaqueDataDescriptor = '';

    public string $opaqueDataValue = '';

    public string $last4 = '';

    public ?string $error = null;

    public function mount(PortalClaimService $claims): void
    {
        try {
            $claims->assertCanFileClaim($this->client());
        } catch (ValidationException $e) {
            $this->gateError = $e->validator->errors()->first('claim');
        }
    }

    public function file(PortalClaimService $claims): mixed
    {
        if ($this->gateError) {
            return null;
        }

        $data = $this->validate([
            'system' => 'required',
            'issue' => 'required',
            'brand' => 'required',
            'problem' => 'required|string',
            'last_time_working_detail' => 'required|string',
            'serial_no' => 'nullable|string',
            'location' => 'nullable|string',
            'model_no' => 'nullable|string',
            'expiry' => ['required', 'regex:/^(0[1-9]|1[0-2])\/?([0-9]{2})$/'],
            'opaqueDataDescriptor' => 'required|string',
            'opaqueDataValue' => 'required|string',
            'last4' => 'required|digits:4',
        ]);

        [$month, $year] = array_pad(explode('/', str_replace(' ', '', $data['expiry'])), 2, '');

        try {
            $claim = $claims->fileClaim($this->client(), $data, new PaymentInput(
                paymentType: '0',
                expMonth: $month,
                expYear: strlen($year) === 2 ? '20'.$year : $year,
                opaqueDataDescriptor: $data['opaqueDataDescriptor'],
                opaqueDataValue: $data['opaqueDataValue'],
                explicitLast4: $data['last4'],
            ));
        } catch (ValidationException $e) {
            $this->gateError = $e->validator->errors()->first('claim') ?: __('We could not file your claim.');

            return null;
        } catch (\Throwable $e) {
            report($e);
            $this->error = __('We could not process the service-call fee. Please check your card and try again.');

            return null;
        }

        return $this->redirectRoute('portal.claims.show', ['claim' => $claim->id], navigate: true);
    }

    private function client(): Client
    {
        return auth('user')->user();
    }

    public function render(): View
    {
        return view('livewire.portal.claims.create', [
            'systems' => System::orderBy('title')->get(),
            'issues' => $this->system ? Issue::where('system_id', $this->system)->orderBy('title')->get() : collect(),
            'brands' => Brand::orderBy('title')->get(),
            'callFee' => (float) ($this->client()->call_fee ?: 0),
        ])->layout('layouts.public', ['title' => __('File a claim')]);
    }
}
