<?php

namespace App\Livewire\Dashboard;

use App\Services\Reporting\DashboardMetricsService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * The admin dashboard. Ported from the legacy DashboardController@dashboard —
 * the KPI tiles, the 12-month financial + customer trend charts, the claim
 * status mix, and this month's salesman performance + lead funnel. Heavy
 * aggregates are cached for 5 minutes (as in the legacy).
 */
class Index extends Component
{
    public function refresh(): void
    {
        Cache::forget('dashboard_metrics_v1_'.now()->format('Y-m'));
        Cache::forget('dashboard_expected_earnings_v1');

        Flux::toast(variant: 'success', text: __('Dashboard refreshed.'));
    }

    public function render(DashboardMetricsService $metrics): View
    {
        return view('livewire.dashboard.index', [
            'm' => $metrics->all(),
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
