<?php

namespace App\Http\Controllers\Claims;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams the current Claims filter as CSV (plain GET, browser-native download). */
class ExportController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $query = Claim::query()
            ->with(['client:id,first_name,last_name,policy_number', 'technician:id,first_name,last_name,business_name'])
            ->search($request->query('search'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('technician'), fn ($q) => $q->where('tech_id', $request->query('technician')));

        $filename = 'claims-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'claim_no', 'customer', 'policy_number', 'technician', 'owed_price', 'total_price', 'status', 'opened_at']);

            $query->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, [
                        $c->id,
                        $c->claim_no,
                        $c->client ? trim($c->client->first_name.' '.$c->client->last_name) : '',
                        $c->client->policy_number ?? '',
                        $c->technician?->full_name ?? '',
                        $c->owed_price,
                        $c->total_price,
                        ClaimStatus::tryLabel((int) $c->status),
                        optional($c->created_at)->toDateString(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
