<?php

use App\Livewire\RealEstate\Auth\Login;
use App\Livewire\RealEstate\Auth\Register;
use App\Livewire\RealEstate\Dashboard;
use App\Livewire\RealEstate\Orders\Create as OrderCreate;
use App\Livewire\RealEstate\Orders\Show as OrderShow;
use App\Livewire\RealEstate\Settings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Real Estate sub-product (Phase 12d)
|--------------------------------------------------------------------------
| Guard `agent` against the `agents` table. Legacy `/agent` prefix and
| route names kept (`agent.login`, `agent.register`, `realesate.home` —
| misspelling and all). The order wizard lands in Phase 12d-2.
*/

Route::prefix('agent')->group(function () {
    Route::get('login', Login::class)->name('agent.login');
    Route::get('register', Register::class)->name('agent.register');

    Route::middleware('agent.auth')->group(function () {
        Route::get('realestate', Dashboard::class)->name('realesate.home');
        Route::get('dashboard', Dashboard::class)->name('agent.dashboard');
        Route::get('settings', Settings::class)->name('realestate.settings');

        Route::get('new/order', OrderCreate::class)->name('agent.order.create');
        Route::get('order/{order}', OrderShow::class)->name('agent.order.show');
    });
});
