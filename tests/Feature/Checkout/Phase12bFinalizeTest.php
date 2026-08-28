<?php

use App\Livewire\Checkout\Funnel;
use App\Livewire\Checkout\Payment;
use App\Mail\PolicyNumberMail;
use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\CustomPlanPrice;
use App\Models\Package;
use App\Models\PaymentHistory;
use App\Models\Promotion;
use App\Models\WlInquiry;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\PaymentInput;
use App\Services\Checkout\CheckoutFunnelService;
use App\Services\Checkout\WlCheckoutFinalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function checkoutMerchant(): AuthorizeAccount
{
    return AuthorizeAccount::create([
        'name' => 'Primary', 'environment' => 'sandbox',
        'api_login_id' => 'l', 'transaction_key' => 'k',
        'is_active' => true, 'is_default_charge' => true, 'is_default_client' => true, 'weight' => 100,
    ]);
}

function planForCheckout(float $monthly = 60, float $yearly = 600): Package
{
    $package = Package::create(['title' => 'Total Home', 'monthly' => (string) $monthly, 'yearly' => (string) $yearly]);
    CustomPlanPrice::create([
        'package_id' => $package->id,
        'original_monthly' => $monthly, 'original_yearly' => $yearly,
        'new_monthly' => $monthly, 'new_yearly' => $yearly,
    ]);

    return $package;
}

/** Drive the funnel through to a payment-ready session and return the package. */
function primeFunnel(int $term = 1, ?string $promo = null): Package
{
    $package = planForCheckout();
    if ($promo) {
        Promotion::create(['name' => $promo, 'amount' => 10, 'promotion_type' => 1]);
    }

    $c = Livewire::test(Funnel::class)
        ->set('zip', '90210')->call('submitZip')
        ->set('firstName', 'Dana')->set('lastName', 'Reed')
        ->set('email', 'dana@example.com')->set('contact', '8185550142')->call('submitContact')
        ->call('selectPlan', $package->id)->call('toReview')
        ->call('setTerm', $term);

    if ($promo) {
        $c->set('promoCode', $promo)->call('applyPromo');
    }

    return $package;
}

/** @return array<string,string> A dry-run-acceptable payment form payload. */
function payForm(array $o = []): array
{
    return array_merge([
        'address' => '5 Palm Ave', 'city' => 'LA', 'state' => 'CA',
        'expMonth' => '09', 'expYear' => (string) (now()->year + 3),
        'opaqueDataDescriptor' => 'COMMON.ACCEPT.INAPP.PAYMENT',
        'opaqueDataValue' => 'DRYRUN-token', 'last4' => '1111', 'brand' => 'Visa',
    ], $o);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    checkoutMerchant();
});

test('chargeOnce charges without creating a subscription', function () {
    $client = Client::factory()->yearly()->create(['status' => 0]);

    $history = app(CheckoutService::class)->chargeOnce($client, 600, PaymentInput::fromArray([
        'opaqueDataDescriptor' => 'COMMON.ACCEPT.INAPP.PAYMENT', 'opaqueDataValue' => 'tok', 'last4' => '4242',
    ]));

    expect($history->transaction_id)->toStartWith('DRYRUN-')
        ->and($history->subscription_id)->toBeNull()
        ->and($history->amount)->toEqual(600.0)
        ->and($client->fresh()->status)->toBe(1);
});

test('finalising a monthly funnel creates an active WL policy with a subscription', function () {
    $package = primeFunnel(term: 1);

    $client = app(WlCheckoutFinalizer::class)->finalize(payForm());

    expect($client->client_type)->toBe('WL')
        ->and($client->payment_structure)->toBe('1')
        ->and((int) $client->package)->toBe($package->id)
        ->and($client->status)->toBe(1)
        ->and((int) $client->bypass_payment)->toBe(0)
        ->and($client->policy_number)->toMatch('/^\d{3}-\d{3}-\d{3}$/')
        ->and($client->price_cal_response)->not->toBeNull();

    $history = PaymentHistory::where('client_id', $client->id)->first();
    expect($history->amount)->toEqual(60.0)
        ->and($history->subscription_id)->toStartWith('DRYRUN-SUB-')
        ->and(CardProfile::where('client_id', $client->id)->exists())->toBeTrue();

    expect(WlInquiry::where('zip_code', 90210)->value('step'))->toBe(8);
    Mail::assertSent(PolicyNumberMail::class);
});

test('a multi-year funnel charges once, sets an end date and no subscription', function () {
    primeFunnel(term: 4); // 3 years

    $client = app(WlCheckoutFinalizer::class)->finalize(payForm());

    expect($client->payment_structure)->toBe('2')
        ->and($client->end_date)->not->toBeNull();

    $history = PaymentHistory::where('client_id', $client->id)->first();
    expect($history->subscription_id)->toBeNull()
        ->and($history->amount)->toEqual(1800.0); // 600 * 3
});

test('the promo discount is applied to the charged amount', function () {
    primeFunnel(term: 1, promo: 'save10');

    $client = app(WlCheckoutFinalizer::class)->finalize(payForm());

    expect($client->promo_code)->toBe('save10')
        ->and(PaymentHistory::where('client_id', $client->id)->value('amount'))->toEqual(50.0); // 60 - 10
});

test('the finalizer clears the funnel session', function () {
    primeFunnel(term: 1);
    app(WlCheckoutFinalizer::class)->finalize(payForm());

    expect(app(CheckoutFunnelService::class)->state())->toBe([]);
});

test('finalising with no funnel session is refused', function () {
    expect(fn () => app(WlCheckoutFinalizer::class)->finalize(payForm()))
        ->toThrow(RuntimeException::class, 'checkout_session_missing');
});

test('the payment component pays and redirects to the thank-you page', function () {
    primeFunnel(term: 1);

    Livewire::test(Payment::class)
        ->set('address', '5 Palm Ave')->set('city', 'LA')->set('state', 'CA')
        ->set('expiry', '09/'.substr((string) (now()->year + 3), -2))
        ->set('opaqueDataDescriptor', 'COMMON.ACCEPT.INAPP.PAYMENT')
        ->set('opaqueDataValue', 'DRYRUN-token')
        ->set('last4', '1111')
        ->call('pay')
        ->assertRedirect(route('thankyou'));

    expect(Client::where('client_type', 'WL')->count())->toBe(1);
});

test('the payment component rejects a submission with no card token', function () {
    primeFunnel(term: 1);

    Livewire::test(Payment::class)
        ->set('address', '5 Palm Ave')
        ->set('expiry', '09/30')
        ->call('pay')
        ->assertHasErrors(['opaqueDataValue', 'last4']);

    expect(Client::count())->toBe(0);
});
