<?php

use App\Livewire\Reporting\FinanceCockpit as FinanceCockpitScreen;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Models\SaleCommission;
use App\Models\User;
use App\Services\Reporting\FinanceCockpitService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function fc(): FinanceCockpitService
{
    return app(FinanceCockpitService::class);
}

test('the summary nets commission and refunds out of gross revenue', function () {
    $client = Client::factory()->create();
    $when = Carbon::now()->startOfMonth()->addDay();

    PaymentHistory::create(['client_id' => $client->id, 'amount' => 200, 'payment_status' => 1, 'claim_payment' => 0, 'refund_payment' => 0, 'is_custom' => 0, 'created_at' => $when]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 50, 'refund_amount' => 50, 'payment_status' => 1, 'claim_payment' => 0, 'refund_payment' => 1, 'is_custom' => 0, 'created_at' => $when]);
    SaleCommission::create(['user_id' => 1, 'client_id' => $client->id, 'commision_price' => 30, 'created_at' => $when]);

    $range = fc()->resolveRange(1);
    $summary = fc()->summary($range['from'], $range['to']);

    expect((float) $summary['gross_revenue'])->toBe(200.0)
        ->and((float) $summary['refunds'])->toBe(50.0)
        ->and((float) $summary['commission_expense'])->toBe(30.0)
        ->and((float) $summary['estimated_net'])->toBe(120.0); // 200 - 50 - 0 - 0 - 30
});

test('the monthly series returns one row per requested month', function () {
    expect(fc()->monthlySeries(3))->toHaveCount(3)
        ->and(fc()->monthlySeries(6))->toHaveCount(6);

    $series = fc()->monthlySeries(3);
    expect($series[array_key_last($series)]['label'])->toBe(Carbon::now()->format('M Y'));
});

test('top salesmen and clients rank by commission and revenue', function () {
    $rep = User::factory()->create(['status' => 1, 'role_id' => '4', 'first_name' => 'Rey']);
    $client = Client::factory()->create(['salesman_name' => 'Rey']);
    $when = Carbon::now()->startOfMonth()->addDay();

    SaleCommission::create(['user_id' => $rep->id, 'client_id' => $client->id, 'commision_price' => 90, 'created_at' => $when]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 400, 'payment_status' => 1, 'claim_payment' => 0, 'refund_payment' => 0, 'is_custom' => 0, 'created_at' => $when]);

    $range = fc()->resolveRange(1);

    $salesmen = fc()->topSalesmen($range['from'], $range['to']);
    expect($salesmen->first()->id)->toBe($rep->id)
        ->and((float) $salesmen->first()->commission_total)->toBe(90.0)
        ->and((float) $salesmen->first()->billed_total)->toBe(400.0);

    $clients = fc()->topClients($range['from'], $range['to']);
    expect((int) $clients->first()->client_id)->toBe($client->id)
        ->and((float) $clients->first()->revenue_total)->toBe(400.0);
});

test('the finance cockpit screen is permission-gated and switches tabs', function () {
    Client::factory()->create();

    Livewire::actingAs(User::factory()->withRole(['Customers'])->create())
        ->test(FinanceCockpitScreen::class)->assertForbidden();

    Livewire::actingAs(User::factory()->withRole(['Settings'])->create())
        ->test(FinanceCockpitScreen::class)
        ->assertOk()
        ->assertSee('Gross revenue')
        ->set('tab', 'ledger')->assertSee('Estimated Net')
        ->set('tab', 'salesmen')->assertSee('Commission')
        ->assertHasNoErrors();
});
