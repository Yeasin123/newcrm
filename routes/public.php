<?php

use App\Http\Controllers\Public\BlogController;
use App\Http\Controllers\Public\LeadFormController;
use App\Http\Controllers\Public\PageController;
use App\Livewire\Checkout\Funnel;
use App\Livewire\Checkout\Payment;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketing site (Phase 12a)
|--------------------------------------------------------------------------
| Guest-facing pages. Legacy route names are preserved. The checkout funnel
| (12b) and the customer portal (12c) bolt on to the `shop.planes` /
| `login.user` names, which are stubs until then.
*/

Route::get('/', [PageController::class, 'home'])->name('homepage');
Route::get('/success', [PageController::class, 'thankYou'])->name('thankyou');
Route::get('/privacy/policy', [PageController::class, 'privacy'])->name('privacy.policy');
Route::get('/sample-policy', [PageController::class, 'samplePolicy'])->name('sample.policy');
Route::get('/terms-and-conditions', [PageController::class, 'terms'])->name('terms.conditions');

Route::get('/about', [PageController::class, 'about'])->name('about.us');
Route::post('/about/contact', [PageController::class, 'storeContact'])->name('about.us.store');

Route::get('/blog', [BlogController::class, 'index'])->name('blogs.index');
Route::get('/blog/{blog:slug}', [BlogController::class, 'show'])->name('blogs.show');

Route::get('/partner', [LeadFormController::class, 'partnerForm'])->name('partner');
Route::post('/partner', [LeadFormController::class, 'partnerStore'])->name('partner.store');
Route::get('/buyer-seller', [LeadFormController::class, 'buyerSellerForm'])->name('buyer.seller');
Route::post('/buyer-seller', [LeadFormController::class, 'buyerSellerStore'])->name('buyer.seller.store');

// --- Plan-purchase funnel (Phase 12b) ---------------------------------------
// One Livewire wizard replaces the three legacy checkout controllers. The
// legacy landing paths (/shopPlans, /shop, /WL) all resolve to it so old
// links keep working; `shop.planes` stays the canonical route name.
Route::get('/plans', Funnel::class)->name('shop.planes');
foreach (['/shopPlans', '/shop', '/WL'] as $legacyPath) {
    Route::get($legacyPath, Funnel::class);
}
Route::get('/checkout/payment', Payment::class)->name('checkout.payment');

// --- Customer portal (Phase 12c) — owns `login.user` + the `/client` prefix.
require __DIR__.'/portal.php';

// --- Real Estate sub-product (Phase 12d) — the `/agent` prefix + `agent` guard.
require __DIR__.'/agent.php';
