<?php

use App\Livewire\Activity\Index as ActivityIndex;
use App\Livewire\BoardHistory\Index as BoardHistoryIndex;
use App\Livewire\Salesmen\Index as SalesmenIndex;
use App\Livewire\Salesmen\Show as SalesmenShow;
use App\Models\Client;
use App\Models\ClientButtonTrack;
use App\Models\ClientRenewButtonTrack;
use App\Models\Role;
use App\Models\SaleCommission;
use App\Models\SalesmanPayroll;
use App\Models\TrackSalesmanAction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

test('the team roster groups by role and filters by the role tabs', function () {
    $salesRole = Role::create(['name' => 'Real Salesman AHC', 'permissions' => []]);
    $rep = User::factory()->create(['first_name' => 'Rainmaker', 'role_id' => $salesRole->id]);
    User::factory()->superAdmin()->create(['first_name' => 'Chief']);
    SaleCommission::factory()->for($rep, 'salesman')->create(['client_id' => 42]);

    Livewire::test(SalesmenIndex::class)
        ->assertSee('Rainmaker')
        ->assertSee('Chief')
        ->call('setRole', 'salesman')
        ->assertSee('Rainmaker')
        ->assertDontSee('Chief')
        ->call('setRole', 'admin')
        ->assertSee('Chief')
        ->assertDontSee('Rainmaker');
});

test('add team member creates a users row', function () {
    Livewire::test(SalesmenIndex::class)
        ->set('tmFirst', 'Newbie')
        ->set('tmEmail', 'newbie@example.com')
        ->call('addTeamMember')
        ->assertHasNoErrors();

    expect(User::where('email', 'newbie@example.com')->exists())->toBeTrue();
});

test('the pay-period view totals unpaid commissions and Mark Paid records a payroll', function () {
    $this->travelTo(Carbon::parse('2026-08-25 10:00:00'));
    $sm = User::factory()->create(['first_name' => 'Solo']);
    SaleCommission::factory()->for($sm, 'salesman')->create(['commision_price' => 100, 'price' => 620, 'store_date' => now()]);
    SaleCommission::factory()->for($sm, 'salesman')->create(['commision_price' => 30, 'price' => 55, 'store_date' => now()]);

    Livewire::test(SalesmenShow::class, ['salesman' => $sm])
        ->assertSee('Current Pay Period')
        ->assertSee('$130.00')   // gross earned this period
        ->call('markPaid');

    $payroll = SalesmanPayroll::where('user_id', $sm->id)->first();
    expect($payroll)->not->toBeNull()
        ->and((int) $payroll->sales_count)->toBe(2)
        ->and((float) $payroll->net)->toBe(130.0)
        ->and(SaleCommission::where('user_id', $sm->id)->where('is_paid', 1)->count())->toBe(2);
});

test('the all-sales view renders lifetime KPIs', function () {
    $sm = User::factory()->create(['first_name' => 'Veteran']);
    SaleCommission::factory()->for($sm, 'salesman')->create(['client_id' => 7, 'price' => 500]);

    Livewire::test(SalesmenShow::class, ['salesman' => $sm])
        ->set('view', 'all-sales')
        ->assertSee('Complete sales history')
        ->assertSee('Monthly Sales Volume')
        ->assertSee('Plan Mix');
});

test('the activity feed filters by salesman and action', function () {
    $a = User::factory()->create(['first_name' => 'Active']);
    $b = User::factory()->create(['first_name' => 'Quiet']);
    TrackSalesmanAction::create(['lead_id' => 1, 'user_id' => $a->id, 'send_sms' => now()->toDateTimeString(), 'STORE_DATE' => now()]);
    TrackSalesmanAction::create(['lead_id' => 2, 'user_id' => $b->id, 'click_to_call' => now()->toDateTimeString(), 'STORE_DATE' => now()]);

    Livewire::test(ActivityIndex::class)
        ->set('salesman', (string) $a->id)
        ->assertSee('href="'.route('leads.show', 1).'"', false)
        ->assertDontSee('href="'.route('leads.show', 2).'"', false)
        ->set('salesman', '')
        ->set('action', 'send_sms')
        ->assertSee('href="'.route('leads.show', 1).'"', false)
        ->assertDontSee('href="'.route('leads.show', 2).'"', false);
});

test('board history merges the payment-failed and renewal call boards', function () {
    $agent = User::factory()->create(['first_name' => 'Kay', 'last_name' => 'Taylan']);
    $c1 = Client::factory()->create(['first_name' => 'King', 'last_name' => 'Harris']);
    $c2 = Client::factory()->create(['first_name' => 'Justin', 'last_name' => 'Hail']);

    ClientButtonTrack::create(['client_id' => $c1->id, 'user_id' => $agent->id, 'create_date' => now()->toDateTimeString(), 'button_text' => 'Recovered', 'comments' => 'Payment collected — $54.99']);
    ClientButtonTrack::create(['client_id' => $c2->id, 'user_id' => $agent->id, 'create_date' => now()->toDateTimeString(), 'button_text' => 'No Answer', 'comments' => '']);
    ClientRenewButtonTrack::create(['client_id' => $c2->id, 'user_id' => $agent->id, 'create_date' => now()->toDateTimeString(), 'button_text' => 'Spoke With', 'comments' => 'left message']);

    Livewire::test(BoardHistoryIndex::class)
        ->assertSee('King Harris')
        ->assertSee('Payment Collected')
        ->assertSee('Kay Taylan')
        ->assertSee('Spoke With')
        ->set('outcome', 'no_answer')
        ->assertSee('Justin Hail')
        ->assertDontSee('King Harris');
});

test('board history can scope to renewals only', function () {
    $agent = User::factory()->create();
    $c = Client::factory()->create(['first_name' => 'Renew', 'last_name' => 'Only']);
    ClientButtonTrack::create(['client_id' => $c->id, 'user_id' => $agent->id, 'create_date' => now()->toDateTimeString(), 'button_text' => 'Recovered', 'comments' => 'PF-ROW-MARKER']);
    ClientRenewButtonTrack::create(['client_id' => $c->id, 'user_id' => $agent->id, 'create_date' => now()->toDateTimeString(), 'button_text' => 'Renewed!', 'comments' => 'RN-ROW-MARKER']);

    Livewire::test(BoardHistoryIndex::class)
        ->set('board', 'renewals')
        ->assertSee('RN-ROW-MARKER')
        ->assertDontSee('PF-ROW-MARKER');
});
