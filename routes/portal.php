<?php

use App\Livewire\Portal\Agreement;
use App\Livewire\Portal\Auth\Login;
use App\Livewire\Portal\Claims\Create as ClaimCreate;
use App\Livewire\Portal\Claims\Index as ClaimsIndex;
use App\Livewire\Portal\Claims\Show as ClaimShow;
use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\PackageDetails;
use App\Livewire\Portal\Profile;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer portal (Phase 12c)
|--------------------------------------------------------------------------
| Guard `user` against the `clients` table. Legacy paths and route names
| kept: `/user/login` (login.user), the `/client` prefix. Dashboard,
| package details, profile land in 12c-2; claims + agreement in 12c-3.
*/

Route::get('/user/login', Login::class)->name('login.user');

Route::middleware('portal')->prefix('client')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('portal.dashboard');
    Route::get('/package/details', PackageDetails::class)->name('portal.package');
    Route::get('/profile', Profile::class)->name('portal.profile');
    Route::get('/agreement/policy/view', Agreement::class)->name('portal.agreement');

    Route::get('/claim', ClaimsIndex::class)->name('portal.claims.index');
    Route::get('/claim/add', ClaimCreate::class)->name('portal.claims.create');
    Route::get('/claim/detail/{claim}', ClaimShow::class)->name('portal.claims.show');
});
