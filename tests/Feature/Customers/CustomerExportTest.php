<?php

use App\Models\Client;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

test('export streams a CSV of the filtered segment', function () {
    Client::factory()->monthly()->create(['first_name' => 'Manny', 'last_name' => 'Monthly']);
    Client::factory()->yearly()->create(['first_name' => 'Yolanda', 'last_name' => 'Yearly']);

    $response = $this->get(route('customers.export', ['segment' => 'monthly']));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('policy_number')
        ->and($csv)->toContain('Manny')
        ->and($csv)->not->toContain('Yolanda');
});

test('export requires the Customers permission', function () {
    $this->actingAs(User::factory()->withRole(['Search'])->create());

    $this->get(route('customers.export'))->assertForbidden();
});
