<?php

namespace App\Services\RealEstate;

use App\Models\Client;
use App\Models\RealEstate\Agent;
use App\Models\RealEstate\AgentOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Places a Real Estate order. Port of
 * RealEstate\OrderController::finalStep — writes the `agent_orders` row and
 * a pending `clients` row (payment_structure yearly, status 0, attributed to
 * the agent). No money moves here; a rep follows up to collect payment.
 *
 * The legacy Dompdf invoice render + the multi-recipient invoice email are
 * deferred (Phase 13, alongside the other PDF work).
 */
class AgentOrderService
{
    private const AGENT_ORDER_PACKAGE_ID = 103;

    private const CALL_FEE = 85;

    /**
     * @param  array{
     *   property: array<string, mixed>,
     *   plan: array{name: string, price: float},
     *   buyer: array{name: string, email: string, phone: string},
     *   addons?: array<int, array{id:int, name:string, price:float}>,
     *   closing?: array<string, mixed>|null,
     *   buyer_agent?: array<string, mixed>|null,
     *   seller_agent?: array<string, mixed>|null,
     * }  $wizard
     * @return array{order: AgentOrder, client: Client}
     */
    public function place(Agent $agent, array $wizard): array
    {
        $property = $wizard['property'];
        $buyer = $wizard['buyer'];
        $addons = $wizard['addons'] ?? [];

        $addonIds = array_map(fn ($a) => (int) $a['id'], $addons);
        $addonTotal = array_sum(array_map(fn ($a) => (float) $a['price'], $addons));
        $total = round((float) $wizard['plan']['price'] + $addonTotal, 2);
        $priceResponse = ['price' => $total, 'discount_price' => 0];

        $policyNumber = sprintf('%d-%d-%d', random_int(100, 999), random_int(100, 999), random_int(100, 999));

        return DB::transaction(function () use ($agent, $wizard, $property, $buyer, $addons, $addonIds, $priceResponse, $policyNumber) {
            $order = AgentOrder::create([
                'agent_id' => $agent->id,
                'plan_name' => $wizard['plan']['name'],
                'address_one' => $property['address_one'] ?? null,
                'address_two' => $property['address_two'] ?? null,
                'name' => $buyer['name'],
                'email' => $buyer['email'],
                'phone' => $buyer['phone'],
                'stepproperty' => $property + ['planName' => $wizard['plan']['name'], 'planPrice' => $wizard['plan']['price']],
                'stepbuyer' => $buyer,
                'addondata' => $addons ?: null,
                'closingInfo' => $wizard['closing'] ?? null,
                'buyerAgent' => $wizard['buyer_agent'] ?? null,
                'sellerAgent' => $wizard['seller_agent'] ?? null,
            ]);

            $client = Client::create([
                'policy_number' => $policyNumber,
                'password' => Hash::make($policyNumber),
                'first_name' => $buyer['name'],
                'email' => $buyer['email'],
                'contact' => $buyer['phone'],
                'address' => $property['address_one'] ?? null,
                'city' => $property['city'] ?? null,
                'state' => $property['state'] ?? null,
                'zip_code' => $property['postal_code'] ?? null,
                'payment_structure' => '2',
                'property_size' => $property['property_size'] ?? null,
                'property_type' => $property['property_name'] ?? null,
                'package' => self::AGENT_ORDER_PACKAGE_ID,
                'services' => $addonIds ? json_encode($addonIds) : null,
                'bypass_payment' => 0,
                'call_fee' => self::CALL_FEE,
                'status' => 0,
                'agent_id' => $agent->id,
                'price_cal_response' => json_encode($priceResponse),
                'start_date' => null,
                'created_at' => Carbon::now(),
            ]);

            return ['order' => $order, 'client' => $client];
        });
    }
}
