<?php

use App\Events\LeadCreated;
use App\Livewire\Checkout\Funnel;
use App\Models\Addon;
use App\Models\CustomPlanPrice;
use App\Models\Inquiry;
use App\Models\LeadDetail;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\Webhook;
use App\Models\WlInquiry;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function seedFunnelPlan(): Package
{
    $package = Package::create(['title' => 'Total Home', 'monthly' => '49.99', 'yearly' => '499']);
    CustomPlanPrice::create([
        'package_id' => $package->id,
        'original_monthly' => 49.99, 'original_yearly' => 499,
        'new_monthly' => 49.99, 'new_yearly' => 499,
    ]);

    return $package;
}

test('the funnel landing page renders for guests', function () {
    $this->get(route('shop.planes'))->assertOk()->assertSee('Where is your home?');
});

test('legacy checkout paths resolve to the funnel', function () {
    foreach (['/shopPlans', '/shop', '/WL'] as $path) {
        $this->get($path)->assertOk()->assertSeeLivewire(Funnel::class);
    }
});

test('the zip step advances and records a wl_inquiry', function () {
    Livewire::test(Funnel::class)
        ->set('zip', '90210')
        ->call('submitZip')
        ->assertSet('step', 2);

    expect(WlInquiry::where('zip_code', 90210)->where('step', 1)->exists())->toBeTrue();
});

test('the contact step creates the lead, inquiry and WL webhook flag once', function () {
    Event::fake([LeadCreated::class]);

    Livewire::test(Funnel::class)
        ->set('zip', '90210')->call('submitZip')
        ->set('firstName', 'Dana')->set('lastName', 'Reed')
        ->set('email', 'dana@example.com')->set('contact', '(818) 555-0142')
        ->call('submitContact')
        ->assertSet('step', 3);

    $lead = LeadDetail::first();
    expect($lead)->not->toBeNull()
        ->and($lead->campaign_id)->toBe((int) config('checkout.lead_campaign_id'))
        ->and($lead->lead_type)->toBe('Shop')
        ->and($lead->lead_tag)->toBe('Inquiry')
        ->and($lead->contact)->toBe('8185550142')
        ->and($lead->progress_bar)->toBe(2);

    expect(Inquiry::where('lead_id', $lead->id)->where('shop', 'shop')->exists())->toBeTrue()
        ->and(Webhook::where('lead_id', $lead->id)->where('WL', 'WL')->count())->toBe(1);

    Event::assertDispatched(LeadCreated::class, fn ($e) => $e->lead->is($lead));
});

test('an existing lead matched on phone is refreshed, not duplicated', function () {
    LeadDetail::create(['contact' => '8185550142', 'first_name' => 'Old', 'campaign_id' => 5]);

    Livewire::test(Funnel::class)
        ->set('zip', '90210')->call('submitZip')
        ->set('firstName', 'Dana')->set('lastName', 'Reed')
        ->set('email', 'dana@example.com')->set('contact', '8185550142')
        ->call('submitContact');

    expect(LeadDetail::where('contact', '8185550142')->count())->toBe(1)
        ->and(LeadDetail::where('contact', '8185550142')->value('first_name'))->toBe('Dana')
        ->and(LeadDetail::where('contact', '8185550142')->value('campaign_id'))->toBe(5);
});

test('selecting a plan and add-ons writes through to the lead', function () {
    $package = seedFunnelPlan();
    $addon = Addon::create(['title' => 'Pool coverage', 'monthly' => '12', 'yearly' => '120']);

    $component = Livewire::test(Funnel::class)
        ->set('zip', '90210')->call('submitZip')
        ->set('firstName', 'Dana')->set('lastName', 'Reed')
        ->set('email', 'dana@example.com')->set('contact', '8185550142')->call('submitContact')
        ->call('selectPlan', $package->id)
        ->assertSet('step', 4)
        ->call('toggleAddon', $addon->id)
        ->call('toReview')
        ->assertSet('step', 5);

    $lead = LeadDetail::first();
    expect((int) $lead->package)->toBe($package->id)
        ->and($lead->progress_bar)->toBe(4)
        ->and($lead->addons)->toBe(json_encode(['Pool coverage']));

    $component->assertSee('Due today')->assertSee('61.99'); // 49.99 + 12
});

test('a valid promo code discounts the review total and an invalid one errors', function () {
    $package = seedFunnelPlan();
    Promotion::create(['name' => 'save10', 'amount' => 10, 'promotion_type' => 1]);

    $component = Livewire::test(Funnel::class)
        ->set('zip', '90210')->call('submitZip')
        ->set('firstName', 'Dana')->set('lastName', 'Reed')
        ->set('email', 'dana@example.com')->set('contact', '8185550142')->call('submitContact')
        ->call('selectPlan', $package->id)->call('toReview')
        ->set('promoCode', 'SAVE10')->call('applyPromo')
        ->assertSet('promo.name', 'save10')
        ->assertSee('39.99');

    $component->call('removePromo')->assertSet('promo', null)
        ->set('promoCode', 'nope')->call('applyPromo')
        ->assertHasErrors('promoCode');
});

test('continue to payment redirects to the payment route', function () {
    $package = seedFunnelPlan();

    Livewire::test(Funnel::class)
        ->set('zip', '90210')->call('submitZip')
        ->set('firstName', 'Dana')->set('lastName', 'Reed')
        ->set('email', 'dana@example.com')->set('contact', '8185550142')->call('submitContact')
        ->call('selectPlan', $package->id)->call('toReview')
        ->call('goToPayment')
        ->assertRedirect(route('checkout.payment'));
});

test('the payment page bounces visitors with no funnel state back to plans', function () {
    $this->get(route('checkout.payment'))->assertRedirect(route('shop.planes'));
});
