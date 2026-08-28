<?php

namespace App\Services\Reporting;

use App\Models\Client;
use App\Models\Entrance;
use App\Models\LeadDetail;
use App\Models\SmsText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Entrance "yes" sale-attribution report. Ported from
 * WebhookReportController::entranceReport — per day, per entrance channel: the
 * count of affirmative SMS replies, how many of those leads became a sale /
 * has-one / doobie / inquiry, and the estimated revenue attributed to that
 * channel (matched lead → client by email / phone → price_cal_response).
 *
 * The entrance_key → label map is the report's own (a few keys differ from the
 * live inbound config), so it is kept verbatim here.
 */
class EntranceReportService
{
    public const ENTRANCE_KEYS = [
        'EGKORLQELI8XN2375' => 'zero',
        'ENTRANCE8HEVSK9WKA4055' => 'one',
        'ENTRANCEOZDRLSBF064681' => 'two',
        'ENTRANCEPR1PXOJM534480' => 'three',
        'ENTRANCEES3WHIRPZS4596' => 'four',
        'EGK4AI8QNS0E84665' => 'five',
        'EGKQ0TGHATGLZ4681' => 'six',
        'ENTRANCEXFS30B58WB5099' => 'seven',
        'ENTRANCEO9IF7WYVMF5100' => 'eight',
        'EGKMHE9TBNT665101' => 'nine',
        'D1SDuLTV8xZHyf1jWdET' => 'mm',
        'Y6TtQlYURjTPFyBn4bQe' => 'yair',
        'TMZY83IoNMLUTq40D6sS' => 'avchad',
    ];

    /** @return LengthAwarePaginator<int,array> */
    public function dailyRows(int $perPage = 50): LengthAwarePaginator
    {
        $words = SmsText::query()->pluck('sms_word')->map(fn ($w) => strtolower((string) $w))->filter()->values();

        $dateKey = $this->dateKey('created_at');

        $base = Entrance::query()
            ->whereNotNull('account_key')
            ->when($words->isNotEmpty(), fn ($q) => $q->whereIn(DB::raw('LOWER(message)'), $words->all()))
            ->when($words->isEmpty(), fn ($q) => $q->whereRaw('1 = 0'));

        $days = (clone $base)
            ->selectRaw("$dateKey as day")
            ->selectRaw('COUNT(*) as total_yes')
            ->groupByRaw($dateKey)
            ->orderByRaw("$dateKey desc")
            ->paginate($perPage);

        $days->getCollection()->transform(fn ($row) => $this->buildDay((string) $row->day, (int) $row->total_yes, $words));

        return $days;
    }

    protected function buildDay(string $day, int $totalYes, $words): array
    {
        $out = ['day' => $day, 'total_yes' => $totalYes, 'channels' => []];

        foreach (self::ENTRANCE_KEYS as $key => $label) {
            $yesLeadIds = Entrance::query()
                ->where('account_key', $key)
                ->whereDate('created_at', $day)
                ->when($words->isNotEmpty(), fn ($q) => $q->whereIn(DB::raw('LOWER(message)'), $words->all()))
                ->whereNotNull('lead_detail_id')
                ->distinct()->pluck('lead_detail_id')->all();

            if ($yesLeadIds === []) {
                $out['channels'][$label] = ['yes' => 0, 'sale' => 0, 'has_one' => 0, 'doobie' => 0, 'inquiry' => 0, 'sale_price' => 0.0];

                continue;
            }

            $countByStatus = fn ($status) => LeadDetail::query()->whereIn('id', $yesLeadIds)->where('status', $status)->count();
            $saleLeads = LeadDetail::query()->whereIn('id', $yesLeadIds)->where('status', 1)->get(['id', 'email', 'contact']);

            $out['channels'][$label] = [
                'yes' => count($yesLeadIds),
                'sale' => $saleLeads->count(),
                'has_one' => $countByStatus(2),
                'doobie' => $countByStatus(3),
                'inquiry' => LeadDetail::query()->whereIn('id', $yesLeadIds)->where('lead_tag', 'Inquiry')->count(),
                'sale_price' => $this->salePriceFromLeads($saleLeads),
            ];
        }

        return $out;
    }

    protected function salePriceFromLeads(Collection $saleLeads): float
    {
        if ($saleLeads->isEmpty()) {
            return 0.0;
        }

        $clients = $this->matchingClients(
            $saleLeads->pluck('email')->all(),
            $saleLeads->pluck('contact')->all(),
        );

        if ($clients->isEmpty()) {
            return 0.0;
        }

        return (float) $saleLeads
            ->map(function ($lead) use ($clients) {
                $email = $this->normalizeEmail($lead->email);
                $variants = $this->contactVariants($lead->contact);

                return $clients->first(function ($c) use ($email, $variants) {
                    $ce = $this->normalizeEmail($c->email);
                    $cc = $this->contactVariants($c->contact);

                    return ($email && $ce && $email === $ce) || ($variants && array_intersect($variants, $cc));
                });
            })
            ->filter()
            ->unique('id')
            ->sum(fn ($c) => $this->extractClientPrice($c->price_cal_response));
    }

    protected function matchingClients(array $emails, array $contacts): Collection
    {
        $emails = collect($emails)->map(fn ($e) => $this->normalizeEmail($e))->filter()->unique()->values()->all();
        $contacts = collect($contacts)->flatMap(fn ($c) => $this->contactVariants($c))->filter()->unique()->values()->all();

        if ($emails === [] && $contacts === []) {
            return new Collection;
        }

        $normalizedContact = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact, '(', ''), ')', ''), '-', ''), ' ', ''), '+', ''), '.', '')";

        return Client::query()
            ->where(function ($q) use ($emails, $contacts, $normalizedContact) {
                if ($emails !== []) {
                    $q->whereIn(DB::raw('LOWER(TRIM(email))'), $emails);
                }
                if ($contacts !== []) {
                    $emails !== []
                        ? $q->orWhereIn(DB::raw($normalizedContact), $contacts)
                        : $q->whereIn(DB::raw($normalizedContact), $contacts);
                }
            })
            ->get(['id', 'email', 'contact', 'price_cal_response']);
    }

    protected function normalizeEmail(?string $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = strtolower(trim($email));

        return $email !== '' ? $email : null;
    }

    /** @return array<int,string> */
    protected function contactVariants(?string $contact): array
    {
        if (empty($contact)) {
            return [];
        }

        $clean = preg_replace('/\D+/', '', $contact) ?? '';
        if ($clean === '') {
            return [];
        }

        $variants = [$clean];
        if (strlen($clean) === 11 && str_starts_with($clean, '1')) {
            $variants[] = substr($clean, 1);
        }
        if (strlen($clean) === 10) {
            $variants[] = '1'.$clean;
        }

        return array_values(array_unique($variants));
    }

    protected function extractClientPrice(?string $priceCalResponse): float
    {
        $decoded = json_decode((string) $priceCalResponse, true);
        if (! is_array($decoded)) {
            return 0.0;
        }

        $discount = (float) str_replace(',', '', (string) ($decoded['discount_price'] ?? 0));

        return $discount > 0
            ? $discount
            : (float) str_replace(',', '', (string) ($decoded['price'] ?? 0));
    }

    protected function dateKey(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "date($column)"
            : "DATE($column)";
    }
}
