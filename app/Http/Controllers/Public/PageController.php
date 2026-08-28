<?php

namespace App\Http\Controllers\Public;

use App\Models\Banner;
use App\Models\BasicSettings;
use App\Models\ContactUs;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The static / CMS-driven public marketing pages. Ported from
 * Web\HomeController + Web\AboutUsController — same routes and page semantics;
 * the Bootstrap templates are replaced with clean Tailwind.
 */
class PageController
{
    public function home(): View
    {
        $this->clearFunnelSession();

        return view('public.home', [
            'bs' => BasicSettings::first(),
            'banner' => Banner::first(),
        ]);
    }

    public function about(): View
    {
        return view('public.about', ['bs' => BasicSettings::first()]);
    }

    public function storeContact(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'contact' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:4000'],
        ]);

        ContactUs::create($data + ['created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        return back()->with('success', __('Information saved successfully.'));
    }

    public function privacy(): View
    {
        return view('public.privacy');
    }

    public function terms(): View
    {
        return view('public.terms');
    }

    public function samplePolicy(): View
    {
        return view('public.sample-policy');
    }

    public function thankYou(): View
    {
        $this->clearFunnelSession();

        return view('public.thank-you');
    }

    protected function clearFunnelSession(): void
    {
        session()->forget(['inquiry_id', 'stepOneData', 'steptwoData', 'shop_zipcode', 'shop_city', 'shop_state']);
    }
}
