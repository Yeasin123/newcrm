<?php

namespace App\Services\Reporting;

use App\Models\Webhook;
use Illuminate\Support\Collection;

/**
 * Daily inbound-webhook activity report. Ported from
 * WebhookReportController::webhookReport / webhookReportDetails — a per-day
 * count for each source-flag column on the `webhooks` table, and a drill-down
 * to the rows behind one day + source.
 */
class WebhookReportService
{
    /** Source columns shown in the report, in display order. */
    public const SOURCES = ['CEL', 'Klay', 'WL', 'EN', 'MM', 'BHC', 'QUOTE', 'INST', 'LG', 'CS', 'YAIR', 'AVCHAD'];

    public function dailyCounts(): Collection
    {
        $cases = collect(self::SOURCES)
            ->map(fn ($col) => "SUM(CASE WHEN $col IS NOT NULL THEN 1 ELSE 0 END) as {$col}_count")
            ->implode(', ');

        return Webhook::query()
            ->selectRaw('DATE(STORE_DATE) as date, '.$cases.', COUNT(*) as total')
            ->whereNotNull('STORE_DATE')
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();
    }

    public function detail(string $date, string $type): Collection
    {
        abort_unless(in_array($type, self::SOURCES, true), 404);

        return Webhook::query()
            ->whereDate('STORE_DATE', $date)
            ->whereNotNull($type)
            ->with('leadDetail:id,first_name,last_name,contact,email,status,campaign_id')
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    }
}
