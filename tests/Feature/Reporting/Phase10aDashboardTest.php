<?php

use App\Livewire\Dashboard\Index;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Models\User;
use App\Services\Reporting\DashboardMetricsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(fn () => Cache::flush());

function dashMetrics(): array
{
    return app(DashboardMetricsService::class)->all();
}

test('the metrics service returns a stable shape with zeros on an empty database', function () {
    $m = dashMetrics();

    expect($m)->toHaveKeys(['scalars', 'monthly_financials', 'financial_chart', 'operational_chart'])
        ->and($m['financial_chart']['labels'])->toHaveCount(12)
        ->and($m['operational_chart']['customer_trend']['labels'])->toHaveCount(12)
        ->and($m['operational_chart']['claim_status']['values'])->toHaveCount(10)
        ->and((float) $m['scalars']['month_earnings'])->toBe(0.0)
        ->and($m['monthly_financials']['collection_rate'])->toBe(0);
});

test('a paid policy payment this month lifts the earnings and gross-revenue figures', function () {
    $client = Client::factory()->create(['status' => 1]);
    PaymentHistory::create([
        'client_id' => $client->id, 'amount' => 120.50, 'payment_status' => 1,
        'claim_payment' => 0, 'refund_payment' => 0, 'is_custom' => 0,
        'created_at' => Carbon::now()->startOfMonth()->addDay(),
    ]);

    $m = dashMetrics();

    expect((float) $m['scalars']['month_earnings'])->toBe(120.5)
        ->and((float) $m['scalars']['total_earnings'])->toBe(120.5)
        ->and((float) $m['monthly_financials']['gross_revenue'])->toBe(120.5)
        ->and($m['monthly_financials']['successful_payments'])->toBe(1)
        ->and($m['monthly_financials']['payment_attempts'])->toBe(1)
        ->and($m['monthly_financials']['collection_rate'])->toBe(100.0)
        ->and(end($m['financial_chart']['gross_revenue']))->toBe(120.5);
});

test('a refunded policy payment is reflected in the refund figures', function () {
    $client = Client::factory()->create();
    PaymentHistory::create([
        'client_id' => $client->id, 'amount' => 80, 'refund_amount' => 80,
        'payment_status' => 1, 'claim_payment' => 0, 'refund_payment' => 1, 'is_custom' => 0,
        'created_at' => Carbon::now()->startOfMonth()->addDay(),
    ]);

    $m = dashMetrics();

    expect((float) $m['scalars']['total_refund'])->toBe(80.0)
        ->and($m['scalars']['refunded_clients'])->toBe(1)
        ->and((float) $m['monthly_financials']['refunds'])->toBe(80.0);
});

test('the dashboard screen renders the tiles and charts', function () {
    Client::factory()->count(2)->create(['status' => 1]);

    Livewire::actingAs(User::factory()->superAdmin()->create())
        ->test(Index::class)
        ->assertOk()
        ->assertSee('Active customers')
        ->assertSee('Revenue — last 12 months')
        ->assertSee('Salesman performance')
        ->call('refresh')
        ->assertHasNoErrors();
});
