<?php

namespace App\Http\Middleware;

use App\Services\FailedPayment\FailedPaymentSettingsService;
use Closure;
use Illuminate\Http\Request;

class SyncFailedPaymentSettings
{
    public function __construct(protected FailedPaymentSettingsService $settings) {}

    public function handle(Request $request, Closure $next)
    {
        $this->settings->syncConfig();

        return $next($request);
    }
}
