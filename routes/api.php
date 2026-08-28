<?php

use App\Http\Controllers\Api\AnetNotifyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authorize.Net notify webhooks (unauthenticated — HMAC-verified in the
| controller, always responds 200). Paths kept identical to the legacy app.
|--------------------------------------------------------------------------
*/
Route::post('transaction/create/notify', [AnetNotifyController::class, 'create'])->name('anet.notify.create');
Route::post('transaction/suspend/notify', [AnetNotifyController::class, 'suspend'])->name('anet.notify.suspend');
Route::post('transaction/terminated/notify', [AnetNotifyController::class, 'terminated'])->name('anet.notify.terminated');
