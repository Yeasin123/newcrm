<?php

namespace App\Livewire\PaymentHealth;

use App\Models\PaymentHealthEvent;
use App\Services\Billing\PaymentHealthReport;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        if ($this->month === '') {
            $this->month = now()->format('Y-m');
        }
    }

    protected function monthCarbon(): Carbon
    {
        try {
            return Carbon::parse($this->month.'-01');
        } catch (\Throwable $e) {
            return now()->startOfMonth();
        }
    }

    public function saveSnapshot(PaymentHealthReport $report): void
    {
        $report->persist($this->monthCarbon());
        Flux::toast(variant: 'success', text: __('Snapshot saved for :m.', ['m' => $this->month]));
    }

    public function resolveEvent(int $id): void
    {
        PaymentHealthEvent::whereKey($id)->update(['status' => 'resolved', 'resolved_at' => now()]);
        Flux::toast(variant: 'success', text: __('Event resolved.'));
    }

    public function render(PaymentHealthReport $report): View
    {
        return view('livewire.payment-health.index', [
            'metrics' => $report->forMonth($this->monthCarbon()),
            'openEvents' => PaymentHealthEvent::query()
                ->where('status', 'open')
                ->with('client:id,first_name,last_name,policy_number')
                ->latest('id')
                ->limit(50)
                ->get(),
        ])->layout('layouts.app', ['title' => 'Payment Health']);
    }
}
