<?php

use App\Livewire\Commissions\Index;
use App\Livewire\Commissions\Settings;
use App\Models\BillingSetting;
use App\Models\Client;
use App\Models\SaleCommission;
use App\Models\SalesmanPayroll;
use App\Models\User;
use App\Services\Commission\CommissionCalculator;
use App\Services\Commission\CommissionService;
use App\Services\Commission\CommissionSettingsService;
use App\Services\Commission\SalesmanCommissionService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $this->travelTo(Carbon::parse('2026-08-25 10:00:00'));
});

test('the commissions dashboard shows the salesman breakdown and pending payout', function () {
    $sm = User::factory()->create(['first_name' => 'Sales', 'last_name' => 'Person']);
    SaleCommission::factory()->for($sm, 'salesman')->count(3)->create(['commision_price' => 50, 'store_date' => now()]);
    SaleCommission::factory()->for($sm, 'salesman')->paid()->create(['commision_price' => 99, 'store_date' => now()]);

    Livewire::test(Index::class)
        ->assertSee('Sales Person')
        ->assertSee('$150.00'); // pending / est payout for the current period (paid row excluded)
});

test('Run Payroll marks the current period paid and records payroll rows', function () {
    $sm = User::factory()->create(['first_name' => 'Runner']);
    SaleCommission::factory()->for($sm, 'salesman')->count(2)->create(['commision_price' => 40, 'store_date' => now()]);

    Livewire::test(Index::class)->call('runPayroll');

    expect(SaleCommission::where('user_id', $sm->id)->where('is_paid', 1)->count())->toBe(2)
        ->and(SalesmanPayroll::where('user_id', $sm->id)->value('sales_count'))->toBe(2)
        ->and((float) SalesmanPayroll::where('user_id', $sm->id)->value('net'))->toBe(80.0);
});

test('markPaid skips cancelled rows', function () {
    $ok = SaleCommission::factory()->create();
    $cancelled = SaleCommission::factory()->cancelled()->create();

    $n = app(CommissionService::class)->markPaid([$ok->id, $cancelled->id]);

    expect($n)->toBe(1)
        ->and($cancelled->fresh()->is_paid)->toBeFalse();
});

test('commission settings persist to billing_settings and round-trip', function () {
    $sm = User::factory()->create(['first_name' => 'Ovr']);

    Livewire::test(Settings::class)
        ->set('cfg.monthly_pct', 92)
        ->set('cfg.billing_amount', 250)
        ->set("cfg.overrides.{$sm->id}", 12)
        ->call('addTier')
        ->call('save')
        ->assertHasNoErrors();

    expect(BillingSetting::where('key', 'commission_config')->exists())->toBeTrue();

    $cfg = app(CommissionSettingsService::class)->all();
    expect((float) $cfg['plan_rates']['monthly'])->toBe(0.92)
        ->and((float) $cfg['billing_spliffy']['amount'])->toBe(250.0)
        ->and((float) $cfg['overrides'][$sm->id])->toBe(12.0)
        ->and(app(CommissionSettingsService::class)->rateFor($sm->id))->toBe(12.0);
});

test('the plan-type rate, after-hours multiplier and 2x window all apply to a sale', function () {
    $calc = app(CommissionCalculator::class);
    $monthly = Client::factory()->create(['payment_structure' => '1']);

    // default monthly rate 90%
    expect($calc->forSale($monthly, 100, now()->setTime(12, 0))['amount'])->toBe(90.0);

    // change the rate -> commission changes
    app(CommissionSettingsService::class)->save(array_merge(app(CommissionSettingsService::class)->all(), [
        'plan_rates' => ['monthly' => 0.80, 'yearly_full' => 0.099, 'yearly_inst' => 0.08],
        'after_hours' => ['enabled' => true, 'cutoff' => 19, 'multiplier' => 1.5],
        'two_x_event' => ['date' => '2026-08-27', 'start' => '18:00', 'end' => '20:00'],
    ]));

    expect($calc->forSale($monthly, 100, Carbon::parse('2026-08-25 12:00'))['amount'])->toBe(80.0)   // rate only
        ->and($calc->forSale($monthly, 100, Carbon::parse('2026-08-25 20:00'))['amount'])->toBe(120.0) // 80 x 1.5 (after hours)
        ->and($calc->forSale($monthly, 100, Carbon::parse('2026-08-27 19:00'))['amount'])->toBe(240.0); // 80 x 1.5 x 2 (2x window)
});

test('the Spliffy bonus stacks configured tiers by card count', function () {
    $svc = app(SalesmanCommissionService::class);

    expect($svc->spiffFor(2))->toBe(0.0)     // below tier 1
        ->and($svc->spiffFor(3))->toBe(65.0) // tier 1
        ->and($svc->spiffFor(7))->toBe(195.0); // tier 1 + tier 2
});

test('awarding the weekly Billing Spliffy writes a bonus commission for the top biller', function () {
    $top = User::factory()->create(['first_name' => 'Topper']);
    SaleCommission::factory()->for($top, 'salesman')->create(['price' => 5000, 'commision_price' => 40, 'store_date' => now()]);

    Livewire::test(Index::class)->call('awardBillingSpliffy');

    $bonus = SaleCommission::where('user_id', $top->id)->where('type', 'bonus')->first();
    expect($bonus)->not->toBeNull()
        ->and((float) $bonus->commision_price)->toBe(200.0);
});

test('the Commissions permission gates the page', function () {
    $this->actingAs(User::factory()->withRole(['Search'])->create());

    $this->get(route('commissions.index'))->assertForbidden();
    $this->get(route('commissions.settings'))->assertForbidden();
});
