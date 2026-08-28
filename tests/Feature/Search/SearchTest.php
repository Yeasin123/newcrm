<?php

use App\Livewire\Search\Index;
use App\Models\Client;
use App\Models\LeadDetail;
use App\Models\Package;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

test('a name filter under three characters runs nothing', function () {
    Client::factory()->create(['first_name' => 'Bartholomew']);

    Livewire::test(Index::class)
        ->set('cf.name', 'Ba')
        ->call('search')
        ->assertSee('at least one filter')
        ->assertDontSee('Bartholomew');
});

test('the customers form finds a customer and links to the detail page', function () {
    $package = Package::create(['title' => 'Platinum', 'monthly' => '50', 'yearly' => '500']);
    $hit = Client::factory()->create(['first_name' => 'Sharedname', 'last_name' => 'Customer', 'status' => 1, 'package' => $package->id, 'salesman_name' => 'Danny F']);
    Client::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);

    Livewire::test(Index::class)
        ->set('cf.name', 'Sharedname')
        ->call('search')
        ->assertSee('Sharedname Customer')
        ->assertDontSee('Someone Else')
        ->assertSee('Platinum')
        ->assertSee('Danny F')
        ->assertSeeHtml(route('customers.show', $hit->id));
});

test('the leads tab finds a lead and offers Convert to Client', function () {
    $lead = LeadDetail::factory()->create(['first_name' => 'Lead', 'last_name' => 'One', 'email' => 'target@example.com']);
    LeadDetail::factory()->create(['first_name' => 'Lead', 'last_name' => 'Two', 'email' => 'nope@example.com']);

    Livewire::test(Index::class)
        ->call('setTab', 'leads')
        ->set('lf.email', 'target@example.com')
        ->call('search')
        ->assertSee('Lead One')
        ->assertDontSee('Lead Two')
        ->assertSee('Convert to Client')
        ->assertSeeHtml(route('sale-sheet.index', ['lead' => $lead->id]));
});

test('clear empties every filter', function () {
    Livewire::test(Index::class)
        ->set('cf.name', 'Whoever')
        ->set('smart', 'something')
        ->call('search')
        ->call('clear')
        ->assertSet('cf.name', '')
        ->assertSet('smart', '')
        ->assertSet('searched', false);
});

test('smart search falls back to a keyword name search when the LLM is off', function () {
    Client::factory()->create(['first_name' => 'Persephone', 'last_name' => 'Quill']);

    Livewire::test(Index::class)
        ->set('smart', 'Persephone')
        ->call('runSmart')
        ->assertSet('cf.name', 'Persephone')
        ->assertSee('Persephone Quill')
        ->assertSee('keyword search');
});

test('the Search permission gates the page', function () {
    $this->actingAs(User::factory()->withRole(['Customers'])->create());

    $this->get(route('search.index'))->assertForbidden();
});
