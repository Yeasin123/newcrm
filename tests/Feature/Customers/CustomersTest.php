<?php

use App\Livewire\Customers\Index;
use App\Livewire\Customers\Show;
use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\Note;
use App\Models\Package;
use App\Models\PaymentHistory;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
});

test('the customers index lists policies', function () {
    Client::factory()->count(3)->create();

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee('customers found');
});

test('load more grows the row limit', function () {
    Client::factory()->count(40)->create();

    Livewire::test(Index::class)
        ->assertSet('perPage', 30)
        ->call('loadMore')
        ->assertSet('perPage', 60);
});

test('the tab counts reflect each segment', function () {
    Client::factory()->monthly()->count(2)->create();
    Client::factory()->yearly()->create();

    Livewire::test(Index::class)
        ->assertViewHas('counts', fn ($c) => $c['all'] === 3 && $c['monthly'] === 2 && $c['yearly'] === 1);
});

test('search filters the list', function () {
    Client::factory()->create(['first_name' => 'Zebediah', 'last_name' => 'Quark']);
    Client::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);

    Livewire::test(Index::class)
        ->set('search', 'Zebediah')
        ->assertSee('Zebediah Quark')
        ->assertDontSee('Someone Else');
});

test('the monthly segment only shows monthly policies', function () {
    Client::factory()->monthly()->create(['first_name' => 'Manny', 'last_name' => 'Monthly']);
    Client::factory()->yearly()->create(['first_name' => 'Yolanda', 'last_name' => 'Yearly']);

    Livewire::test(Index::class)
        ->call('setSegment', 'monthly')
        ->assertSee('Manny Monthly')
        ->assertDontSee('Yolanda Yearly');
});

test('the edit profile flyout saves contact and policy fields', function () {
    $client = Client::factory()->create(['first_name' => 'Old', 'status' => 1]);

    Livewire::test(Show::class, ['customer' => $client])
        ->call('openProfile')
        ->set('profile.first_name', 'New')
        ->set('profile.email', 'new@example.com')
        ->set('profile.city', 'Philadelphia')
        ->set('profile.status', '5')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertSet('showProfileModal', false);

    $client->refresh();
    expect($client->first_name)->toBe('New')
        ->and($client->email)->toBe('new@example.com')
        ->and($client->city)->toBe('Philadelphia')
        ->and((int) $client->status)->toBe(5);
});

test('the edit plan modal saves plan fields without any billing action', function () {
    $package = Package::create(['title' => 'Platinum', 'monthly' => '54.99', 'yearly' => '540']);
    $client = Client::factory()->create(['payment_structure' => '1']);

    Livewire::test(Show::class, ['customer' => $client])
        ->call('openPlan')
        ->set('epPackage', (string) $package->id)
        ->set('epCallFee', '85')
        ->set('epDiscount', '10')
        ->call('savePlan')
        ->assertHasNoErrors()
        ->assertSet('showPlanModal', false);

    $client->refresh();
    expect((int) $client->package)->toBe($package->id)
        ->and($client->call_fee)->toBe('85')
        ->and($client->discount)->toBe('10');
});

test('the header status dropdown maps onto the legacy client flags', function () {
    $client = Client::factory()->create(['status' => 1, 'login_off' => false]);

    Livewire::test(Show::class, ['customer' => $client])->call('setStatus', 'cancelled');
    $client->refresh();
    expect((int) $client->status)->toBe(0)
        ->and((bool) $client->login_off)->toBeTrue()
        ->and($client->cancle_date)->not->toBeNull();

    Livewire::test(Show::class, ['customer' => $client])->call('setStatus', 'bypass');
    expect((int) $client->fresh()->status)->toBe(5);
});

test('a manual charge row can be removed but a billing row cannot', function () {
    $client = Client::factory()->create();
    $manual = PaymentHistory::create(['client_id' => $client->id, 'amount' => 20, 'is_custom' => 1, 'payment_status' => 1]);
    $billing = PaymentHistory::create(['client_id' => $client->id, 'amount' => 50, 'is_custom' => 0, 'payment_status' => 1]);

    Livewire::test(Show::class, ['customer' => $client])
        ->call('deletePayment', $billing->id)
        ->call('deletePayment', $manual->id);

    expect(PaymentHistory::find($manual->id))->toBeNull()
        ->and(PaymentHistory::find($billing->id))->not->toBeNull();
});

test('the Add Payment Method form saves a card linked to the customer', function () {
    AuthorizeAccount::create([
        'name' => 'M', 'environment' => 'sandbox', 'api_login_id' => 'l', 'transaction_key' => 'k',
        'is_active' => true, 'is_default_charge' => true, 'weight' => 100,
    ]);
    $client = Client::factory()->create();

    Livewire::test(Show::class, ['customer' => $client])
        ->call('addPaymentMethodForm')
        ->set('pmMethod', 'card')
        ->set('pmCardNumber', '4242424242424242')
        ->set('pmExpMonth', '11')
        ->set('pmExpYear', '29')
        ->call('savePaymentMethod')
        ->assertHasNoErrors()
        ->assertSet('showAddPayment', false);

    $profile = CardProfile::where('client_id', $client->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->last4)->toBe('4242')
        ->and($profile->expYear)->toBe('2029');
});

test('archiving a customer soft-deletes and redirects to the list', function () {
    $client = Client::factory()->create();

    Livewire::test(Show::class, ['customer' => $client])
        ->call('deleteCustomer')
        ->assertRedirect(route('customers.index'));

    expect(Client::withTrashed()->find($client->id)->trashed())->toBeTrue();
});

test('a non-permitted role cannot open the customers list', function () {
    $this->actingAs(User::factory()->withRole(['Search'])->create());

    $this->get(route('customers.index'))->assertForbidden();
});

test('toggling status flips clients.status', function () {
    $client = Client::factory()->create(['status' => 1]);

    Livewire::test(Show::class, ['customer' => $client])
        ->call('toggleStatus');

    expect($client->fresh()->status)->toBe(0);
});

test('disabling the portal sets login_off', function () {
    $client = Client::factory()->create(['login_off' => false]);

    Livewire::test(Show::class, ['customer' => $client])
        ->call('toggleLoginOff');

    expect((bool) $client->fresh()->login_off)->toBeTrue();
});

test('an internal note can be added and is attributed', function () {
    $client = Client::factory()->create();

    Livewire::test(Show::class, ['customer' => $client])
        ->set('noteBody', 'Called customer about renewal.')
        ->call('addNote')
        ->assertHasNoErrors();

    $note = Note::where('client_id', (string) $client->id)->first();

    expect($note)->not->toBeNull()
        ->and($note->notes)->toBe('Called customer about renewal.')
        ->and($note->user_id)->toBe((string) auth()->id());
});

test('a blank note is rejected', function () {
    $client = Client::factory()->create();

    Livewire::test(Show::class, ['customer' => $client])
        ->set('noteBody', '')
        ->call('addNote')
        ->assertHasErrors('noteBody');
});
