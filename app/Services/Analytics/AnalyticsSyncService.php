<?php

namespace App\Services\Analytics;

use App\Models\Claim;
use App\Models\ClaimClosedDetail;
use App\Models\Client;
use App\Models\LeadDetail;
use App\Models\PaymentHistory;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors CRM writes into the denormalised analytics_* tables on the
 * `analytics` connection. Ported from the legacy AnalyticsSyncService; the one
 * change is the config('analytics.mirror_enabled') gate at the top of every
 * public method, so the mirror is inert until an operator turns it on and runs
 * `analytics:backfill`.
 */
class AnalyticsSyncService
{
    public function enabled(): bool
    {
        return (bool) config('analytics.mirror_enabled');
    }

    protected function db(): ConnectionInterface
    {
        return DB::connection(config('analytics.connection', 'analytics'));
    }

    /* ---- Client → analytics_customers + analytics_sales_events ---- */

    public function syncClient(Client $client): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->db()->table('analytics_customers')->updateOrInsert(['client_id' => $client->id], $this->customerRow($client));
        $this->db()->table('analytics_sales_events')->updateOrInsert(['client_id' => $client->id], $this->salesEventRow($client));
    }

    public function deleteClient(Client $client): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->db()->table('analytics_customers')->where('client_id', $client->id)->delete();
        $this->db()->table('analytics_sales_events')->where('client_id', $client->id)->delete();
    }

    public function customerRow(Client $client): array
    {
        $now = Carbon::now();

        return [
            'policy_number' => $client->policy_number,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'contact' => $client->contact,
            'city' => $client->city,
            'state' => $client->state,
            'zip_code' => $client->zip_code,
            'payment_structure' => $client->payment_structure,
            'payment_type' => $client->payment_type,
            'installment' => $client->installment,
            'paid_installment' => $client->paid_installment,
            'property_size_id' => $client->property_size,
            'property_type_id' => $client->property_type,
            'package_id' => $client->package,
            'discount' => $client->discount,
            'total_contract_value' => $this->contractValue($client),
            'start_date' => $client->start_date,
            'end_date' => $client->end_date,
            'status' => $client->status,
            'authorize_status' => $client->authorize_status,
            'created_at_client' => $client->created_at,
            'updated_at_client' => $client->updated_at,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    public function salesEventRow(Client $client): array
    {
        $createdAt = $client->created_at ? Carbon::parse($client->created_at) : Carbon::now();
        $now = Carbon::now();

        return [
            'customer_id' => $client->id,
            'policy_number' => $client->policy_number,
            'sale_date' => $createdAt->toDateString(),
            'sale_year' => $createdAt->year,
            'sale_month' => $createdAt->month,
            'sale_day' => $createdAt->day,
            'plan_type' => $client->payment_structure,
            'billing_term_months' => (int) $client->payment_structure === 1 ? 12 : null,
            'total_contract_value' => $this->contractValue($client),
            'first_payment_amount' => null,
            'recurring_amount' => null,
            'is_recurring' => ! empty($client->payment_structure) && (int) $client->payment_structure === 1,
            'campaign_id' => null,
            'channel' => null,
            'source_tag' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    protected function contractValue(Client $client): float
    {
        $price = $client->price_cal_response ? json_decode((string) $client->price_cal_response) : null;

        if (! $price) {
            return 0.0;
        }

        $num = fn ($v) => (float) str_replace(',', '', (string) ($v ?? 0));

        if (! empty($client->installment) && in_array((int) $client->installment, [2, 3, 4, 5], true)) {
            return $num($price->installment_price ?? 0);
        }

        return (float) ($client->discount ?? 0) > 0
            ? $num($price->discount_price ?? 0)
            : $num($price->price ?? 0);
    }

    /* ---- PaymentHistory → analytics_payments ---- */

    public function syncPayment(PaymentHistory $payment): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->db()->table('analytics_payments')->updateOrInsert(['payment_id' => $payment->id], $this->paymentRow($payment));
    }

    public function deletePayment(PaymentHistory $payment): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->db()->table('analytics_payments')->where('payment_id', $payment->id)->delete();
    }

    public function paymentRow(PaymentHistory $payment): array
    {
        $paidAt = $payment->created_at
            ? Carbon::parse($payment->created_at)
            : ($payment->payment_date ? Carbon::parse($payment->payment_date) : Carbon::now());
        $now = Carbon::now();

        return [
            'client_id' => $payment->client_id,
            'amount' => $payment->amount,
            'is_claim_payment' => $payment->claim_payment ? 1 : 0,
            'is_refund_payment' => $payment->refund_payment ? 1 : 0,
            'is_success' => ((int) $payment->payment_status === 1) ? 1 : 0,
            'paid_at' => $paidAt->toDateTimeString(),
            'year' => $paidAt->year,
            'month' => $paidAt->month,
            'day' => $paidAt->day,
            'payment_method' => $payment->payment_type,
            'payment_status_text' => $payment->status,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /* ---- Claim → analytics_claims ---- */

    public function syncClaim(Claim $claim): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->db()->table('analytics_claims')->updateOrInsert(['claim_id' => $claim->id], $this->claimRow($claim));
    }

    public function deleteClaim(Claim $claim): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->db()->table('analytics_claims')->where('claim_id', $claim->id)->delete();
    }

    public function claimRow(Claim $claim): array
    {
        $createdAt = $claim->created_at ? Carbon::parse($claim->created_at) : Carbon::now();
        $now = Carbon::now();

        return [
            'client_id' => $claim->client_id,
            'claim_number' => $claim->claim_no,
            'status' => $claim->status,
            'total_price' => $claim->owed_price ?? $claim->total_price ?? 0,
            'total_spent' => ClaimClosedDetail::where('claim_id', $claim->id)->sum('claim_spent_price'),
            'claim_created_at' => $claim->created_at,
            'claim_updated_at' => $claim->updated_at,
            'year' => $createdAt->year,
            'month' => $createdAt->month,
            'day' => $createdAt->day,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /* ---- LeadDetail → analytics_leads ---- */

    public function syncLead(LeadDetail $lead): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->db()->table('analytics_leads')->updateOrInsert(['lead_id' => $lead->id], $this->leadRow($lead));
    }

    public function deleteLead(LeadDetail $lead): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->db()->table('analytics_leads')->where('lead_id', $lead->id)->delete();
    }

    public function leadRow(LeadDetail $lead): array
    {
        $createdAt = $lead->created_at ? Carbon::parse($lead->created_at) : Carbon::now();
        $now = Carbon::now();

        return [
            'client_id' => $lead->client_id ?? null,
            'name' => trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')),
            'email' => $lead->email,
            'phone' => $lead->contact,
            'source' => $lead->campaign_id,
            'campaign' => $lead->campaign_id,
            'status' => $lead->status,
            'lead_created_at' => $lead->created_at,
            'year' => $createdAt->year,
            'month' => $createdAt->month,
            'day' => $createdAt->day,
            'converted_to_client' => $lead->converted_to_client ?? 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
