<?php

use App\Livewire\RealEstate\Orders\Create;
use App\Models\Addon;
use App\Models\Client;
use App\Models\Package;
use App\Models\PropertySize;
use App\Models\PropertyType;
use App\Models\RealEstate\Agent;
use App\Models\RealEstate\AgentOrder;
use App\Models\RealEstate\Company;
use App\Services\RealEstate\AgentOrderService;
use Livewire\Livewire;

function makeAgent(): Agent
{
    $company = Company::create(['name' => 'Acme Realty', 'type' => 'Real Estate']);

    return Agent::create([
        'company_id' => $company->id, 'name' => 'Ada', 'email' => 'ada@acme.com',
        'password' => bcrypt('secret123'),
    ]);
}

function orderWizard(int $planId, int $sizeId, int $typeId, array $addons = []): array
{
    return [
        'property' => [
            'address_one' => '10 Oak St', 'address_two' => '', 'city' => 'Austin',
            'state' => 'TX', 'postal_code' => '78701',
            'property_size' => $sizeId, 'property_name' => $typeId,
        ],
        'plan' => ['name' => Package::find($planId)->title, 'price' => 600.0],
        'buyer' => ['name' => 'Bill Buyer', 'email' => 'bill@example.com', 'phone' => '5125550100'],
        'addons' => $addons,
        'closing' => ['name' => 'Title Co', 'email' => 'title@example.com'],
        'buyer_agent' => null,
        'seller_agent' => null,
    ];
}

test('placing an order writes the agent_order and a pending client', function () {
    $agent = makeAgent();
    $plan = Package::create(['title' => 'RE Plan', 'yearly' => '600', 'monthly' => '60']);
    $size = PropertySize::create(['title' => 'Up to 5,000 sqft']);
    $type = PropertyType::create(['title' => 'Single family']);
    $addon = Addon::create(['title' => 'Pool', 'yearly' => '100', 'monthly' => '10']);

    $result = app(AgentOrderService::class)->place($agent, orderWizard($plan->id, $size->id, $type->id, [
        ['id' => $addon->id, 'name' => 'Pool', 'price' => 100.0],
    ]));

    $order = $result['order'];
    expect($order)->toBeInstanceOf(AgentOrder::class)
        ->and($order->agent_id)->toBe($agent->id)
        ->and($order->plan_name)->toBe('RE Plan')
        ->and($order->name)->toBe('Bill Buyer')
        ->and($order->stepproperty['city'])->toBe('Austin')
        ->and($order->addondata)->toHaveCount(1);

    $client = $result['client'];
    expect($client->agent_id)->toBe($agent->id)
        ->and($client->payment_structure)->toBe('2')
        ->and((int) $client->status)->toBe(0)
        ->and((int) $client->package)->toBe(103)
        ->and($client->services)->toBe(json_encode([$addon->id]))
        ->and((float) json_decode($client->price_cal_response)->price)->toBe(700.0); // 600 + 100
});

test('the wizard walks all six steps and lands on the order page', function () {
    $agent = makeAgent();
    $plan = Package::create(['title' => 'RE Plan', 'yearly' => '600']);
    $size = PropertySize::create(['title' => 'Small']);
    $type = PropertyType::create(['title' => 'Condo']);

    Livewire::actingAs($agent, 'agent')->test(Create::class)
        ->set('address_one', '10 Oak St')->set('city', 'Austin')->set('state', 'TX')->set('postal_code', '78701')
        ->set('property_size', $size->id)->set('property_name', $type->id)
        ->call('stepProperty')->assertSet('step', 2)
        ->set('planId', $plan->id)->call('stepPlan')->assertSet('step', 3)
        ->set('buyer_name', 'Bill')->set('buyer_email', 'bill@example.com')->set('buyer_phone', '5125550100')
        ->call('stepBuyer')->assertSet('step', 4)
        ->call('stepOptions')->assertSet('step', 5)
        ->call('stepClosing')->assertSet('step', 6)
        ->call('placeOrder')
        ->assertRedirect(route('agent.order.show', AgentOrder::first()->id));

    expect(AgentOrder::where('agent_id', $agent->id)->count())->toBe(1)
        ->and(Client::where('agent_id', $agent->id)->count())->toBe(1);
});

test('property step validation blocks an incomplete first step', function () {
    $agent = makeAgent();

    Livewire::actingAs($agent, 'agent')->test(Create::class)
        ->call('stepProperty')
        ->assertHasErrors(['address_one', 'city', 'state', 'postal_code', 'property_size', 'property_name'])
        ->assertSet('step', 1);
});

test('an order detail page 404s for another agent', function () {
    $mine = makeAgent();
    $companyTwo = Company::create(['name' => 'Other', 'type' => 'Real Estate']);
    $other = Agent::create(['company_id' => $companyTwo->id, 'email' => 'x@x.com', 'password' => bcrypt('x')]);
    $order = AgentOrder::create(['agent_id' => $other->id, 'plan_name' => 'X', 'stepproperty' => [], 'stepbuyer' => []]);

    $this->actingAs($mine, 'agent')
        ->get(route('agent.order.show', $order->id))
        ->assertNotFound();
});
