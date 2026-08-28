<?php

namespace App\Services\Renewals;

use App\Models\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The renewal queue — every active yearly / monthly policy whose effective
 * coverage end (custom_e_date ?? end_date) lands in the next ~4 months and
 * that has not already been marked renewed.
 *
 * Shared by the Renewal Queue list screen and the Renewal call board so the
 * two always walk the same set. The window is computed in PHP: the legacy
 * end-date columns are free-text strings, so a DB-side date filter is not
 * portable across MySQL and the sqlite test schema.
 */
class RenewalQueueService
{
    public const WINDOW_DAYS = 122;

    /** @return Collection<int,object> shaped rows, soonest-expiring first */
    public function window(): Collection
    {
        $today = Carbon::today();
        $limit = $today->copy()->addDays(self::WINDOW_DAYS);

        return Client::query()
            ->whereIn('payment_structure', ['1', '2'])
            ->where('status', 1)
            ->where(fn ($q) => $q->whereNull('renewed')->orWhere('renewed', 0))
            ->select([
                'id', 'policy_number', 'first_name', 'last_name', 'email', 'contact',
                'address', 'city', 'state', 'payment_structure', 'installment', 'package',
                'call_fee', 'services', 'salesman_name', 'start_date', 'custom_s_date',
                'end_date', 'custom_e_date', 'custom_price', 'price_cal_response',
            ])
            ->with('propertyPackage:id,title')
            ->get()
            ->map(function (Client $c) use ($today, $limit) {
                $raw = $c->custom_e_date ?: $c->end_date;
                if (! $raw) {
                    return null;
                }

                try {
                    $end = Carbon::parse($raw)->startOfDay();
                } catch (\Throwable) {
                    return null;
                }

                if ($end->lt($today) || $end->gt($limit)) {
                    return null;
                }

                return $this->shape($c, $end, $today);
            })
            ->filter()
            ->sortBy('days_left')
            ->values();
    }

    /**
     * Ordered client-id list for the board walk.
     *
     * @param  string  $filter  yearly | monthly | all
     * @return list<int>
     */
    public function ids(string $filter = 'yearly'): array
    {
        return $this->window()
            ->when($filter === 'yearly', fn (Collection $r) => $r->where('is_yearly', true))
            ->when($filter === 'monthly', fn (Collection $r) => $r->where('is_yearly', false))
            ->pluck('id')
            ->all();
    }

    /** One shaped row for a client that may sit outside the current window. */
    public function rowFor(int $clientId): ?object
    {
        $client = Client::query()->with('propertyPackage:id,title')->find($clientId);
        if (! $client) {
            return null;
        }

        $raw = $client->custom_e_date ?: $client->end_date;
        try {
            $end = $raw ? Carbon::parse($raw)->startOfDay() : Carbon::today();
        } catch (\Throwable) {
            $end = Carbon::today();
        }

        return $this->shape($client, $end, Carbon::today());
    }

    private function shape(Client $c, Carbon $end, Carbon $today): object
    {
        return (object) [
            'id' => $c->id,
            'name' => trim($c->first_name.' '.$c->last_name) ?: ('Policy #'.$c->policy_number),
            'policy_number' => $c->policy_number,
            'email' => $c->email,
            'contact' => $c->contact,
            'address' => trim(implode(', ', array_filter([$c->address, $c->city, $c->state]))),
            'city' => trim(($c->city ? $c->city.', ' : '').$c->state, ', '),
            'salesman_name' => $c->salesman_name,
            'call_fee' => $c->call_fee,
            'package' => $c->propertyPackage->title ?? '—',
            'addons' => collect(json_decode((string) $c->services, true) ?: [])->map(fn ($v) => (int) $v)->all(),
            'is_yearly' => (string) $c->payment_structure === '2',
            'term_label' => $this->termLabel($c),
            'annual' => $this->annualValue($c),
            'started' => $c->custom_s_date ?: $c->start_date,
            'expires' => $end,
            'renewed_expiry' => $end->copy()->addYear(),
            'days_left' => $today->diffInDays($end, false),
        ];
    }

    public function termLabel(Client $c): string
    {
        if ((string) $c->payment_structure === '1') {
            return __('Monthly');
        }

        return (int) $c->installment >= 2 ? __('1 Year · installments') : __('1 Year plan');
    }

    public function annualValue(Client $c): float
    {
        $decoded = json_decode((string) $c->price_cal_response, true);
        if (is_array($decoded)) {
            foreach (['grand_total', 'total', 'final_price', 'price'] as $key) {
                if (isset($decoded[$key]) && is_numeric($decoded[$key])) {
                    $amount = (float) $decoded[$key];

                    return (string) $c->payment_structure === '1' ? $amount * 12 : $amount;
                }
            }
        }

        $custom = (float) $c->custom_price;
        if ($custom > 0) {
            return (string) $c->payment_structure === '1' ? $custom * 12 : $custom;
        }

        return 0.0;
    }
}
