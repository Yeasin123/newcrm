<?php

use App\Livewire\Technicians\Index;
use App\Livewire\Technicians\Show;
use App\Models\Claim;
use App\Models\Technician;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

test('the technicians list searches and shows claim counts', function () {
    $tech = Technician::factory()->create(['first_name' => 'Wanda', 'last_name' => 'Wrench']);
    Claim::factory()->count(2)->create(['tech_id' => $tech->id]);
    Technician::factory()->create(['first_name' => 'Someone', 'last_name' => 'Other']);

    Livewire::test(Index::class)
        ->set('search', 'Wanda')
        ->assertSee('Wanda Wrench')
        ->assertSee('2')
        ->assertDontSee('Someone Other');
});

test('a technician detail page lists their claims', function () {
    $tech = Technician::factory()->create();
    Claim::factory()->create(['tech_id' => $tech->id, 'claim_no' => 'TDET-1']);

    Livewire::test(Show::class, ['technician' => $tech])
        ->assertSee('TDET-1');
});

test('the Technicians permission gates the page', function () {
    $this->actingAs(User::factory()->withRole(['Claims'])->create());

    $this->get(route('technicians.index'))->assertForbidden();
});
