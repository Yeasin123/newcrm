<?php

namespace App\Http\Controllers\Leads;

use App\Models\CampaignFile;
use App\Models\LeadDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one campaign's leads (honouring the tag / status / search filters
 * passed on the query string) as a CSV. Plain GET so the browser downloads
 * natively — no Livewire round-trip for a large export.
 */
class CampaignExportController
{
    public function __invoke(Request $request, CampaignFile $campaign): StreamedResponse
    {
        $tag = (string) $request->query('tag', '');
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $query = LeadDetail::query()
            ->where('campaign_id', $campaign->id)
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($tag === 'inquiry', fn ($q) => $q->where('lead_tag', 'Inquiry'))
            ->when($tag === 'sms', fn ($q) => $q->where('lead_tag', 'SMS'))
            ->when($tag === 'none', fn ($q) => $q->where(fn ($w) => $w->whereNull('lead_tag')->orWhere('lead_tag', '')))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('contact', 'like', "%{$search}%")));

        $columns = [
            'id', 'first_name', 'last_name', 'contact', 'email',
            'address', 'city', 'state', 'zip_code', 'status',
            'lead_tag', 'lead_type', 'phone_company', 'created_at',
        ];

        $filename = Str::slug($campaign->name ?: 'campaign-'.$campaign->id).'-leads-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            $query->orderBy('id')->toBase()->chunk(1000, function ($rows) use ($out, $columns) {
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($c) => $row->{$c} ?? '', $columns));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
