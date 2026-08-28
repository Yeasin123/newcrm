<?php

namespace App\Services\SaleSheet;

use App\Models\Client;
use App\Models\PaymentHistory;
use App\Services\Billing\AuthorizeAccountSelector;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\PaymentInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The staff "Add Customer" sale. Faithful consolidation of
 * Admin\ClientsController::clientAddPost — creates one `clients` row per
 * property (rows of a multi-property sale share a `multi_property` group
 * key), then runs ONE Authorize.Net checkout for the summed amount through
 * the Phase 6 CheckoutService (dry-run until billing goes live).
 *
 *   - bypass_payment: no charge; every row is activated (status 5,
 *     start_date now) exactly as legacy did;
 *   - otherwise the primary (first) row's payment_structure / installment
 *     drive the checkout for the combined amount, and the sibling rows are
 *     marked active alongside it.
 */
class SaleSheetService
{
    public function __construct(
        private readonly SaleSheetPriceCalculator $pricing,
        private readonly CheckoutService $checkout,
        private readonly AuthorizeAccountSelector $accounts,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $properties
     */
    public function preview(array $properties): SalePreview
    {
        $prices = array_map(fn (array $p) => $this->priceFor($p), $properties);

        $chargedToday = array_sum(array_map(fn (SalePrice $s) => $s->payable, $prices));
        $totalAll = array_sum(array_map(fn (SalePrice $s) => $s->subtotal, $prices));
        $monthly = array_sum(array_map(
            fn (SalePrice $s) => $s->recurring && $s->term === 1 ? $s->payable : 0.0,
            $prices,
        ));
        $anyRecurring = collect($prices)->contains(fn (SalePrice $s) => $s->recurring);

        return new SalePreview(
            properties: $prices,
            totalAllProperties: round($totalAll, 2),
            totalChargedToday: round($chargedToday, 2),
            monthlyBilling: round($monthly, 2),
            startDate: $anyRecurring ? Carbon::now()->addMonth() : Carbon::now(),
        );
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<int, array<string, mixed>>  $properties
     * @return array{clients: Collection<int, Client>, primary: Client, history: ?PaymentHistory}
     */
    public function place(array $client, array $properties, PaymentInput $payment, bool $bypassPayment): array
    {
        if ($properties === []) {
            throw new \InvalidArgumentException('A sale needs at least one property.');
        }

        $account = $this->accounts->pickForCharge() ?? $this->accounts->pickForNewClient();

        if (! $bypassPayment && ! $account) {
            throw new \RuntimeException('No active Authorize.Net account is configured.');
        }

        $group = count($properties) > 1 ? (string) Str::uuid() : null;

        return DB::transaction(function () use ($client, $properties, $payment, $bypassPayment, $account, $group) {
            $rows = collect($properties)->map(
                fn (array $property) => $this->createClientRow($client, $property, $group, $bypassPayment, $account?->id),
            );

            $primary = $rows->first();

            if ($bypassPayment) {
                return ['clients' => $rows, 'primary' => $primary->fresh(), 'history' => null];
            }

            $combined = round(
                collect($properties)->sum(fn (array $p) => $this->priceFor($p)->payable),
                2,
            );

            $split = (int) ($properties[0]['installment_split'] ?? 0);
            $history = $this->checkout->run($primary, $combined, $payment, array_filter([
                'installment_count' => $split ?: null,
            ]));

            if ($rows->count() > 1) {
                Client::whereIn('id', $rows->pluck('id'))
                    ->where('id', '!=', $primary->id)
                    ->update([
                        'status' => 1,
                        'bypass_payment' => 0,
                        'start_date' => $primary->fresh()->start_date,
                        'authorize_account_id' => $primary->fresh()->authorize_account_id,
                    ]);
            }

            return ['clients' => $rows->map->fresh(), 'primary' => $primary->fresh(), 'history' => $history];
        });
    }

    /**
     * @param  array<string, mixed>  $property
     */
    private function priceFor(array $property): SalePrice
    {
        return $this->pricing->calculate(
            term: (int) ($property['payment_term'] ?? 1),
            packageId: isset($property['package']) ? (int) $property['package'] : null,
            addonIds: array_map('intval', $property['addons'] ?? []),
            propertySizeId: isset($property['property_size']) ? (int) $property['property_size'] : null,
            propertyTypeId: isset($property['property_type']) ? (int) $property['property_type'] : null,
            discountPercent: $this->discountPercent($property),
            installmentSplit: ($property['installment_split'] ?? null) ? (int) $property['installment_split'] : null,
        );
    }

    /** @param  array<string, mixed>  $property */
    private function discountPercent(array $property): float
    {
        $applied = filter_var($property['apply_discount'] ?? false, FILTER_VALIDATE_BOOL);

        return $applied ? (float) ($property['discount_percent'] ?? 0) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<string, mixed>  $property
     */
    private function createClientRow(array $client, array $property, ?string $group, bool $bypass, ?int $accountId): Client
    {
        $price = $this->priceFor($property);
        $term = config('sale_sheet.terms')[$price->term] ?? config('sale_sheet.terms')[1];
        $addonIds = array_map('intval', $property['addons'] ?? []);
        $policyNumber = sprintf('%d-%d-%d', random_int(100, 999), random_int(100, 999), random_int(100, 999));

        return Client::create([
            'policy_number' => $policyNumber,
            'password' => Hash::make($policyNumber),
            'first_name' => $client['first_name'] ?? null,
            'last_name' => $client['last_name'] ?? null,
            'email' => $client['email'] ?? null,
            'contact' => $client['contact'] ?? null,
            'salesman_name' => $client['salesman_name'] ?? null,
            'description' => $client['notes'] ?? null,
            'call_fee' => $client['call_fee'] ?? config('sale_sheet.default_call_fee'),
            'address' => $property['address'] ?? null,
            'city' => $property['city'] ?? null,
            'state' => $property['state'] ?? null,
            'zip_code' => $property['zip_code'] ?? null,
            'property_size' => $property['property_size'] ?? null,
            'property_type' => $property['property_type'] ?? null,
            'package' => $property['package'] ?? null,
            'services' => $addonIds ? json_encode($addonIds) : null,
            'discount' => $this->discountPercent($property) ?: null,
            'payment_structure' => (string) $term['structure'],
            'installment' => ($property['installment_split'] ?? null) ? (string) $property['installment_split'] : null,
            'multi_property' => $group,
            'bypass_payment' => $bypass ? 1 : 0,
            'start_date' => $bypass ? Carbon::now() : null,
            'end_date' => $term['end_months'] ? Carbon::now()->addMonths($term['end_months']) : null,
            'status' => $bypass ? 5 : 0,
            'authorize_account_id' => $accountId,
            'price_cal_response' => json_encode($price->toLegacyArray()),
        ]);
    }
}
