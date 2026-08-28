<?php

namespace App\Services\Lead;

use App\Models\LeadDetail;

/**
 * Shared body of the partner campaign lead-intake links
 * (`/{ahc,ahw,lcl,gbl,jltwo}/campaign/lead-store`, `/modanize/campaign/lead-store`).
 * One implementation, driven by a config('campaign_intake.channels.*') row.
 * Ported verbatim from the legacy LeadLeadailApiLinkController /
 * ModanizeCampaignController.
 */
class CampaignLeadIntakeService
{
    /**
     * @param  array<string,mixed>  $data  raw request payload
     * @param  array<string,mixed>  $channel  a config('campaign_intake.channels.*') row
     * @return string 'ok' | 'empty_data'  (byte-identical to legacy)
     */
    public function store(array $data, array $channel): string
    {
        $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
        $phone = str_replace('-', '', (string) ($data[$channel['phone_key']] ?? ''));
        $campaignId = (int) $channel['campaign_id'];

        if (! empty($channel['modernize'])) {
            return $this->storeModernize($data, $phone, $campaignId);
        }

        if ($phone === '') {
            return 'empty_data';
        }

        // Legacy passes each field as `$data[...] ?? null`, so a re-post updates
        // (or nulls) exactly these columns. lead_tag is only in the update set
        // for the two Inquiry channels.
        $attributes = [
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
        ];

        if (! empty($channel['inquiry_tag'])) {
            $attributes['lead_tag'] = 'Inquiry';
        }

        LeadDetail::query()->updateOrCreate(
            ['contact' => $phone, 'campaign_id' => $campaignId],
            $attributes,
        );

        return 'ok';
    }

    protected function storeModernize(array $data, string $phone, int $campaignId): string
    {
        // Legacy skips only when first name, last name AND phone are all empty.
        if (empty($data['first_name']) && empty($data['last_name']) && $phone === '') {
            return 'empty_data';
        }

        LeadDetail::query()->create([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'campaign_id' => $campaignId,
            'contact' => $phone,
            'email' => $data['email'] ?? null,
            'state' => $data['state'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'priority_indx' => 1,
            'show_status' => 1,
            'user_id' => null,
            'priority_checker' => null,
            'progress_bar' => 2,
            'modernize_id' => $data['modernize_id'] ?? null,
            'lead_type' => 'MD',
        ]);

        return 'ok';
    }
}
