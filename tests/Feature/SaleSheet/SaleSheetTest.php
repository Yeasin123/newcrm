<?php

use App\Livewire\SaleSheet\AddCustomer;
use App\Models\AuthorizeAccount;
use App\Models\Client;
use App\Models\LeadDetail;
use App\Models\Package;
use App\Models\PaymentHistory;
use App\Models\PropertySize;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->superAdmin()->create());
    AuthorizeAccount::create([
        'name' => 'Primary', 'environment' => 'sandbox', 'api_login_id' => 'l', 'transaction_key' => 'k',
        'is_active' => true, 'is_default_charge' => true, 'is_default_client' => true, 'weight' => 100,
    ]);
});

test('opening the sale sheet with ?lead= pre-fills the client and first property from the lead', function () {
    $lead = LeadDetail::factory()->create([
        'first_name' => 'Vincent', 'last_name' => 'Robinson',
        'email' => 'vincentrobinson51@gmail.com', 'contact' => '7174187148',
        'address' => '12 Maple Ct', 'city' => 'Reno', 'state' => 'NV', 'zip_code' => '89501',
    ]);

    Livewire::withQueryParams(['lead' => $lead->id])
        ->test(AddCustomer::class)
        ->assertSet('fromLeadId', $lead->id)
        ->assertSet('client.first_name', 'Vincent')
        ->assertSet('client.last_name', 'Robinson')
        ->assertSet('client.email', 'vincentrobinson51@gmail.com')
        ->assertSet('client.contact', '7174187148')
        ->assertSet('properties.0.address', '12 Maple Ct')
        ->assertSet('properties.0.city', 'Reno')
        ->assertSet('properties.0.state', 'NV')
        ->assertSet('properties.0.zip_code', '89501');
});

function saleSheetPlan(): array
{
    return [
        Package::create(['title' => 'Platinum Coverage', 'monthly' => '54.99', 'yearly' => '549']),
        PropertySize::create(['title' => 'Up to 5,000 sqft', 'monthly' => '0', 'yearly' => '0']),
    ];
}

test('the sale sheet renders the add-customer wizard', function () {
    $this->get(route('sale-sheet.index'))
        ->assertOk()
        ->assertSee('Add Customer')
        ->assertSee('Client Information')
        ->assertSee('Create a new home warranty policy');
});

test('the Sale Sheet permission gates the page', function () {
    $this->actingAs(User::factory()->withRole(['Search'])->create());

    $this->get(route('sale-sheet.index'))->assertForbidden();
});

test('the legacy add-customer paths resolve to the wizard', function () {
    $this->get('/add')->assertOk()->assertSeeLivewire(AddCustomer::class);
    $this->get('/salesman/add/client')->assertOk()->assertSeeLivewire(AddCustomer::class);
});

test('properties can be added and removed', function () {
    Livewire::test(AddCustomer::class)
        ->assertCount('properties', 1)
        ->call('addProperty')->assertCount('properties', 2)
        ->call('removeProperty', 0)->assertCount('properties', 1)
        ->call('removeProperty', 0)->assertCount('properties', 1); // never drops below one
});

test('the sidebar preview reflects the selected plan', function () {
    [$plan] = saleSheetPlan();

    Livewire::test(AddCustomer::class)
        ->set('properties.0.package', $plan->id)
        ->set('properties.0.payment_term', 1)
        ->assertSee('54.99');
});

test('submitting the wizard creates an active client and one charge', function () {
    [$plan, $size] = saleSheetPlan();

    Livewire::test(AddCustomer::class)
        ->set('client.first_name', 'Jane')->set('client.last_name', 'Smith')
        ->set('client.email', 'jane@example.com')->set('client.contact', '8887777777')
        ->set('properties.0.address', '123 Main St')->set('properties.0.city', 'LA')
        ->set('properties.0.state', 'CA')->set('properties.0.zip_code', '90210')
        ->set('properties.0.property_size', $size->id)->set('properties.0.package', $plan->id)
        ->set('properties.0.payment_term', 1)
        ->set('expiry', '09/'.substr((string) (now()->year + 3), -2))
        ->set('opaqueDataDescriptor', 'COMMON.ACCEPT.INAPP.PAYMENT')
        ->set('opaqueDataValue', 'DRYRUN-tok')
        ->set('cardLast4', '4242')
        ->call('submit')
        ->assertRedirect();

    $client = Client::first();
    expect($client->first_name)->toBe('Jane')
        ->and((int) $client->status)->toBe(1)
        ->and($client->payment_structure)->toBe('1');
    expect((float) PaymentHistory::first()->amount)->toBe(54.99);
});

test('no-live-charge creates the client without a charge', function () {
    [$plan, $size] = saleSheetPlan();

    Livewire::test(AddCustomer::class)
        ->set('noLiveCharge', true)
        ->set('client.first_name', 'Jane')->set('client.last_name', 'Smith')
        ->set('client.email', 'jane@example.com')->set('client.contact', '8887777777')
        ->set('properties.0.address', '9 Bypass Rd')->set('properties.0.city', 'LA')
        ->set('properties.0.state', 'CA')->set('properties.0.zip_code', '90210')
        ->set('properties.0.property_size', $size->id)->set('properties.0.package', $plan->id)
        ->set('properties.0.payment_term', 1)
        ->call('submit')
        ->assertRedirect();

    expect((int) Client::first()->status)->toBe(5)
        ->and(PaymentHistory::count())->toBe(0);
});

test('a bank-account sale charges the eCheck path', function () {
    [$plan, $size] = saleSheetPlan();

    Livewire::test(AddCustomer::class)
        ->set('paymentMethod', 'bank')
        ->set('client.first_name', 'Jane')->set('client.last_name', 'Smith')
        ->set('client.email', 'jane@example.com')->set('client.contact', '8887777777')
        ->set('properties.0.address', '5 Bank St')->set('properties.0.city', 'LA')
        ->set('properties.0.state', 'CA')->set('properties.0.zip_code', '90210')
        ->set('properties.0.property_size', $size->id)->set('properties.0.package', $plan->id)
        ->set('properties.0.payment_term', 1)
        ->set('bankRouting', '021000021')->set('bankAccount', '123456789')
        ->set('bankName', 'Jane Smith')->set('bankType', 'Checking')
        ->call('submit')
        ->assertRedirect();

    $history = PaymentHistory::first();
    expect($history)->not->toBeNull()
        ->and((int) $history->payment_type)->toBe(1)
        ->and((float) $history->amount)->toBe(54.99);
});

test('a per-property discount reduces the charge', function () {
    [$plan, $size] = saleSheetPlan();

    Livewire::test(AddCustomer::class)
        ->set('noLiveCharge', true)
        ->set('client.first_name', 'Jane')->set('client.last_name', 'Smith')
        ->set('client.email', 'jane@example.com')->set('client.contact', '8887777777')
        ->set('properties.0.address', '7 Disc Rd')->set('properties.0.city', 'LA')
        ->set('properties.0.state', 'CA')->set('properties.0.zip_code', '90210')
        ->set('properties.0.property_size', $size->id)->set('properties.0.package', $plan->id)
        ->set('properties.0.payment_term', 1)
        ->set('properties.0.apply_discount', '1')
        ->set('properties.0.discount_percent', '10')
        ->call('submit');

    expect((float) json_decode(Client::first()->price_cal_response)->price)->toBe(49.49); // 54.99 - 10%
});

test('a draft is restored on reload and can be cleared', function () {
    // Setting a property fires updated(), which writes the draft to the session.
    Livewire::test(AddCustomer::class)->set('client.first_name', 'Drafty');

    Livewire::test(AddCustomer::class)
        ->assertSet('client.first_name', 'Drafty')
        ->assertSet('draftRestored', true)
        ->call('clearDraft')
        ->assertSet('client.first_name', '')
        ->assertSet('draftRestored', false);
});
