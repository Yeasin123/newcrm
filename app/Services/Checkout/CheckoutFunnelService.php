<?php

namespace App\Services\Checkout;

use App\Models\Addon;
use App\Models\Inquiry;
use App\Models\LeadDetail;
use App\Models\Webhook;
use App\Models\WlInquiry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

/**
 * The persistence side of the public checkout funnel — everything the legacy
 * PlanCheckoutController did between steps (create/update the `lead_details`
 * row, mirror it into `inquiries`, stamp the daily `webhooks` WL flag, and
 * advance the `wl_inqueries` step counter) minus the copy-pasted VanillaSoft
 * / Zapier calls, which stay behind config flags elsewhere.
 *
 * Funnel state lives in `session('checkout')` so a page reload or a
 * `wire:navigate` mid-funnel resumes where the visitor left off.
 */
class CheckoutFunnelService
{
    private const SESSION_KEY = 'checkout';

    /** @return array<string, mixed> */
    public function state(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public function put(string $key, mixed $value): void
    {
        $state = $this->state();
        $state[$key] = $value;
        Session::put(self::SESSION_KEY, $state);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state()[$key] ?? $default;
    }

    public function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /** Step 1 — zip capture. */
    public function rememberZip(string $zip): void
    {
        $this->put('zip_code', $zip);
        $this->touchWlInquiry(['zip_code' => $zip], step: 1, matchZip: $zip);
    }

    /**
     * Step 2 — contact capture. Creates the lead on first sight (which fires
     * LeadCreated via the observer) or refreshes an existing lead matched on
     * phone, mirrors it into `inquiries`, and stamps today's WL webhook flag.
     *
     * @param  array{first_name:string,last_name:string,email:string,contact:string}  $data
     * @return array{lead: LeadDetail, inquiry: Inquiry}
     */
    public function captureContact(array $data): array
    {
        $phone = $this->normalizePhone($data['contact']);
        $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL) ?: $data['email'];

        $lead = LeadDetail::where('contact', $phone)->first();

        $attributes = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'contact' => $phone,
            'email' => $email,
            'priority_indx' => 1,
            'show_status' => 1,
            'user_id' => null,
            'lead_tag' => config('checkout.lead_tag'),
            'priority_checker' => null,
        ];

        if ($lead === null) {
            $lead = LeadDetail::create($attributes + [
                'campaign_id' => config('checkout.lead_campaign_id'),
                'progress_bar' => 2,
                'lead_type' => config('checkout.lead_type'),
            ]);
        } else {
            $lead->fill($attributes)->save();
        }

        $inquiry = Inquiry::create([
            'lead_id' => $lead->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'contact' => $phone,
            'email' => $email,
            'shop' => 'shop',
            'progress_bar' => 2,
        ]);

        $this->stampWebhook($lead->id);

        $this->put('lead_id', $lead->id);
        $this->put('inquiry_id', $inquiry->id);
        $this->put('name', ['first_name' => $data['first_name'], 'last_name' => $data['last_name']]);
        $this->put('email', $email);
        $this->put('contact', $phone);

        $this->touchWlInquiry([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'contact' => $phone,
            'zip_code' => $this->get('zip_code'),
        ], step: 4, matchZip: $this->get('zip_code'));

        return ['lead' => $lead, 'inquiry' => $inquiry];
    }

    /** Step 3 — plan selection. */
    public function selectPlan(int $packageId): void
    {
        $this->put('package_id', $packageId);
        $this->advanceLeadAndInquiry(['package' => $packageId, 'property_size' => 1, 'property_type' => 1], 4);

        if ($inquiry = $this->currentInquiry()) {
            $inquiry->update(['package_id' => $packageId, 'property_size' => 1, 'property_type' => 1]);
        }

        $this->touchWlInquiry(['package_id' => $packageId, 'property_size' => 1, 'property_type' => 1], step: 5, matchZip: $this->get('zip_code'));
    }

    /**
     * Step 4 — add-on selection.
     *
     * @param  array<int, int>  $addonIds
     */
    public function setAddons(array $addonIds): void
    {
        $addonIds = array_values(array_unique(array_map('intval', $addonIds)));
        $names = Addon::whereIn('id', $addonIds)->pluck('title')->all();

        $this->put('addon_ids', $addonIds);

        if ($lead = $this->currentLead()) {
            $lead->update(['addons' => $addonIds ? json_encode($names) : null]);
        }
        if ($inquiry = $this->currentInquiry()) {
            $inquiry->update(['addons' => $addonIds ? json_encode($names) : null]);
        }

        $this->touchWlInquiry(['addons' => $addonIds ? json_encode($addonIds) : null], step: 6, matchZip: $this->get('zip_code'));
    }

    public function currentLead(): ?LeadDetail
    {
        $id = $this->get('lead_id');

        return $id ? LeadDetail::find($id) : null;
    }

    public function currentInquiry(): ?Inquiry
    {
        $id = $this->get('inquiry_id');

        return $id ? Inquiry::find($id) : null;
    }

    public function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        return preg_replace('/^1/', '', $digits) ?? $digits;
    }

    private function advanceLeadAndInquiry(array $leadAttributes, int $progressBar): void
    {
        if ($lead = $this->currentLead()) {
            $lead->update($leadAttributes + ['progress_bar' => $progressBar]);
        }
        if ($inquiry = $this->currentInquiry()) {
            $inquiry->update(['progress_bar' => $progressBar]);
        }
    }

    private function stampWebhook(int $leadId): void
    {
        $exists = Webhook::where('lead_id', $leadId)
            ->whereDate('STORE_DATE', Carbon::today())
            ->where('WL', 'WL')
            ->exists();

        if (! $exists) {
            Webhook::create(['lead_id' => $leadId, 'STORE_DATE' => Carbon::now(), 'WL' => 'WL']);
        }
    }

    private function touchWlInquiry(array $attributes, int $step, ?string $matchZip): void
    {
        $row = $matchZip ? WlInquiry::where('zip_code', $matchZip)->first() : null;

        if ($row) {
            $row->update(array_filter($attributes, fn ($v) => $v !== null) + ['step' => $step]);

            return;
        }

        WlInquiry::create($attributes + ['step' => $step]);
    }
}
