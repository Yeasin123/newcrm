<?php

namespace App\Http\Controllers\Public;

use App\Models\BuyerSeller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The two public lead-capture forms. Ported from Web\Partner / Web\BuyerSeller
 * — `partners` and `buyer_sellers` share the same column set.
 */
class LeadFormController
{
    public function partnerForm(): View
    {
        return view('public.partner');
    }

    public function partnerStore(Request $request)
    {
        Partner::create($this->validated($request));

        return back()->with('success', __('Thanks — our team will be in touch.'));
    }

    public function buyerSellerForm(): View
    {
        return view('public.buyer-seller');
    }

    public function buyerSellerStore(Request $request)
    {
        BuyerSeller::create($this->validated($request));

        return back()->with('success', __('Thanks — our team will be in touch.'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:190'],
            'primary_phone' => ['nullable', 'string', 'max:40'],
            'mobile_number' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:190'],
            'fax' => ['nullable', 'string', 'max:40'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'zip' => ['nullable', 'string', 'max:20'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);
    }
}
