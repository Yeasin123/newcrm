<?php

namespace App\Livewire\PaymentFailed;

use App\Models\Client;
use App\Models\ClientButtonTrack;
use App\Models\ClientTrackAction;
use App\Services\Billing\BillingActionService;
use App\Services\FailedPayment\RecoveryControlCenterSettings;
use App\Services\Viewboard\ViewboardTracker;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Payment Failed — the recovery control center. One tabbed screen for the
 * failed-payment queue, a plain-English overview, and every recovery /
 * outreach / charging / safety setting.
 *
 * Everything here is safe by construction: real charges stay behind the
 * billing two-flag, and every outbound channel stays behind config('comms.*').
 * "Test Mode" (the default) never charges a card or mutates a customer record
 * on its own — only an explicit admin click does.
 */
class Index extends Component
{
    use WithPagination;

    /** Recovery-balance ceiling the legacy board displays against each row. */
    private const RETRY_MAX = 15;

    #[Url]
    public string $tab = 'overview';

    #[Url]
    public string $qtab = 'active'; // active | resolved

    #[Url]
    public string $qsearch = '';

    public string $forecastMonth = '';

    /** Full control-center config, bound to the form tabs via wire:model. */
    public array $cfg = [];

    public function mount(RecoveryControlCenterSettings $settings): void
    {
        $this->cfg = $settings->load();
        $this->forecastMonth = now()->format('Y-m');
    }

    private const TABS = ['overview', 'queue', 'outreach', 'schedule', 'timing', 'templates', 'destinations', 'activity', 'analytics', 'safety'];

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'overview';
    }

    public function setQtab(string $tab): void
    {
        $this->qtab = in_array($tab, ['active', 'resolved'], true) ? $tab : 'active';
        $this->resetPage();
    }

    public function updatedQsearch(): void
    {
        $this->resetPage();
    }

    public function save(RecoveryControlCenterSettings $settings): void
    {
        if (($this->cfg['operating_mode'] ?? null) === 'live' && ! $settings->liveModeAvailable()) {
            Flux::toast(variant: 'warning', text: __('Live Mode needs the billing gate to be live first — kept on Paused.'));
        }

        $this->cfg = $settings->save($this->cfg, auth()->id());
        Flux::toast(variant: 'success', text: __('Recovery settings saved.'));
    }

    public function addPattern(string $value = ''): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }
        $patterns = $this->cfg['smart_timing']['admin_patterns'] ?? [];
        if (! in_array($value, $patterns, true)) {
            $patterns[] = $value;
        }
        $this->cfg['smart_timing']['admin_patterns'] = array_values($patterns);
    }

    public function removePattern(int $i): void
    {
        $patterns = $this->cfg['smart_timing']['admin_patterns'] ?? [];
        unset($patterns[$i]);
        $this->cfg['smart_timing']['admin_patterns'] = array_values($patterns);
    }

    /* -------------------------------------------------------- queue actions */

    private function client(int $id): Client
    {
        $client = Client::find($id);
        abort_unless($client, 404);

        return $client;
    }

    public function claimAndCall(int $id, ViewboardTracker $tracker): void
    {
        $tracker->action('failed', $this->client($id), 'click_to_call');
        Flux::toast(variant: 'success', text: __('Call logged — dial the customer from your bar.'));
    }

    public function markContacted(int $id, ViewboardTracker $tracker): void
    {
        $client = $this->client($id);
        $tracker->action('failed', $client, 'save', 'Contacted');
        $tracker->note('failed', $client, 'Contacted from the Payment Failed queue.', 'Contacted');
        Flux::toast(variant: 'success', text: __('Marked contacted.'));
    }

    /**
     * Recovery retry. In Test / Paused mode this only rehearses — no card is
     * touched. In Live Mode (billing gate live) it runs a real recovery charge
     * through BillingActionService and flips the customer active on success.
     */
    public function retry(int $id, ViewboardTracker $tracker, BillingActionService $billing): void
    {
        $client = $this->client($id);
        $live = ($this->cfg['operating_mode'] ?? 'paused') === 'live'
            && config('billing.driver') === 'live'
            && config('billing.processing_mode') === 'live';

        if (! $live) {
            $tracker->note('failed', $client, 'Test Mode retry rehearsed — no card was charged.', 'Retry (Test Mode)');
            Flux::toast(variant: 'warning', text: __('Test Mode: retry rehearsed, nothing was charged.'));

            return;
        }

        try {
            $amount = $this->recoveryAmount($client);
            $billing->chargeCustom($client, $amount, 'Failed-payment recovery retry');
            $client->forceFill(['status' => 1, 'payment_faild_date' => null])->save();
            $tracker->note('failed', $client, 'Recovery retry charged $'.number_format($amount, 2).'.', 'Recovered');
            Flux::toast(variant: 'success', text: __('Recovery charge captured.'));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function markResolved(int $id, ViewboardTracker $tracker): void
    {
        $client = $this->client($id);
        $client->forceFill(['status' => 1, 'payment_faild_date' => null])->save();
        $tracker->note('failed', $client, 'Marked resolved from the Payment Failed queue.', 'Recovered');
        Flux::toast(variant: 'success', text: __('Marked resolved.'));
    }

    /* --------------------------------------------------------- computations */

    private function recoveryAmount(Client $client): float
    {
        $failed = $client->relationLoaded('latestFailedPolicyPaymentHistory')
            ? $client->latestFailedPolicyPaymentHistory
            : $client->latestFailedPolicyPaymentHistory()->first();

        if ($failed && (float) $failed->amount > 0) {
            return (float) $failed->amount;
        }

        return (float) ($client->custom_price ?: 0);
    }

    private function activeQuery(): Builder
    {
        return Client::query()
            ->where('status', 0)
            ->when($this->qsearch !== '', function (Builder $q) {
                $term = '%'.$this->qsearch.'%';
                $q->where(fn (Builder $i) => $i
                    ->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('policy_number', 'like', $term));
            })
            ->orderByDesc('payment_faild_date')
            ->orderByDesc('id');
    }

    private function scheduleString(): string
    {
        return collect($this->cfg['charge_schedule'] ?? [])
            ->map(fn ($r) => __('Day :d at :t', ['d' => $r['day'], 't' => $r['time']]))
            ->implode(' · ');
    }

    private function overview(): array
    {
        $active = (clone $this->activeQuery())->toBase()->getCountForPagination();
        $resolvedSession = ClientButtonTrack::query()
            ->where('button_text', 'Recovered')
            ->where('create_date', '>=', Carbon::today()->toDateTimeString())
            ->count();

        $channels = array_keys(array_filter($this->cfg['outreach']['channels'] ?? []));
        $realChargesLive = config('billing.driver') === 'live' && config('billing.processing_mode') === 'live';

        return [
            'active' => $active,
            'resolved_session' => $resolvedSession,
            'outreach_on' => (bool) ($this->cfg['outreach']['enabled'] ?? false) && $channels !== [],
            'mode' => $this->cfg['operating_mode'] ?? 'paused',
            'schedule' => $this->scheduleString(),
            'channels' => $channels,
            'safeguards' => [
                'real_charges' => $realChargesLive && ($this->cfg['operating_mode'] ?? '') === 'live',
                'emergency_pause' => (bool) ($this->cfg['emergency_pause'] ?? true),
                'outbound_outreach' => (bool) ($this->cfg['outreach']['enabled'] ?? false),
                'audience_mutations' => ($this->cfg['operating_mode'] ?? '') === 'live',
            ],
        ];
    }

    private function queueData(): array
    {
        if ($this->qtab === 'resolved') {
            $resolved = ClientButtonTrack::query()
                ->whereIn('button_text', ['Recovered', 'Contacted'])
                ->with('client:id,first_name,last_name,policy_number,status')
                ->orderByDesc('id')
                ->limit(60)
                ->get();

            return ['resolved' => $resolved, 'rows' => null, 'kpi' => $this->queueKpi()];
        }

        $rows = $this->activeQuery()
            ->with(['propertyPackage:id,title', 'latestFailedPolicyPaymentHistory'])
            ->paginate(20);

        $rows->getCollection()->transform(function (Client $c) {
            $failed = $c->latestFailedPolicyPaymentHistory;
            $attempts = ClientTrackAction::query()->where('client_id', $c->id)->whereNotNull('save')->count();
            $next = $c->payment_faild_date
                ? Carbon::parse($c->payment_faild_date)->addDays((int) ($this->cfg['charge_schedule'][0]['day'] ?? 3))
                : null;

            return (object) [
                'id' => $c->id,
                'name' => $c->full_name,
                'policy_number' => $c->policy_number,
                'amount' => $failed?->amount ?: $c->custom_price,
                'plan_type' => (string) $c->payment_structure === '1' ? __('Monthly') : ((int) $c->installment >= 2 ? __('Installment') : __('Yearly')),
                'failed_at' => $c->payment_faild_date,
                'reason' => __('Declined'),
                'attempts' => $attempts,
                'next_attempt' => $next,
                'transaction_id' => $failed?->subscription_id ?? $failed?->transaction_id ?? '—',
                'account' => $failed?->subscription_id ? __('Subscription') : __('One-time'),
            ];
        });

        return ['rows' => $rows, 'resolved' => null, 'kpi' => $this->queueKpi()];
    }

    private function queueKpi(): array
    {
        $failed = Client::query()->where('status', 0)
            ->with('latestFailedPolicyPaymentHistory')
            ->get(['id', 'custom_price', 'payment_structure']);

        $risk = $failed->sum(fn (Client $c) => (float) ($c->latestFailedPolicyPaymentHistory->amount ?? $c->custom_price ?? 0));

        return [
            'failed' => $failed->count(),
            'risk' => $risk,
            'resolved_session' => ClientButtonTrack::query()
                ->where('button_text', 'Recovered')
                ->where('create_date', '>=', Carbon::today()->toDateTimeString())
                ->count(),
        ];
    }

    private function activityData(): array
    {
        $failed = Client::query()->where('status', 0)
            ->with(['propertyPackage:id,title', 'latestFailedPolicyPaymentHistory', 'cardProfiles:id,client_id,customer_profile_id'])
            ->orderByDesc('payment_faild_date')
            ->limit(40)
            ->get();

        $eligible = $failed->filter(fn (Client $c) => $c->authorize_account_id && $c->cardProfiles->isNotEmpty())->count();

        $rehearsal = $failed->map(function (Client $c) {
            $profile = $c->cardProfiles->first();
            $failedPmt = $c->latestFailedPolicyPaymentHistory;
            $signed = $c->start_date ? Carbon::parse($c->start_date) : null;

            return (object) [
                'id' => $c->id,
                'name' => $c->full_name,
                'policy_number' => $c->policy_number,
                'signed_up' => $signed?->format('M j, Y'),
                'billing_day' => $signed ? $signed->format('jS').' '.__('of month') : '—',
                'amount' => $failedPmt?->amount ?: $c->custom_price,
                'plan' => (string) $c->payment_structure === '1' ? 'monthly' : 'yearly',
                'is_installment' => (int) $c->installment >= 2,
                'anet_customer' => $profile?->customer_profile_id ?: __('Not available'),
                'subscription' => $failedPmt?->subscription_id ?: '—',
                'has_profile' => (bool) $profile,
            ];
        });

        return [
            'pending' => $failed->count(),
            'eligible' => $eligible,
            'needs_review' => max(0, $failed->count() - $eligible),
            'month_attempts' => 0,
            'pacing' => 0,
            'monthly' => $rehearsal->where('is_installment', false),
            'installment' => $rehearsal->where('is_installment', true),
        ];
    }

    private function analyticsData(): array
    {
        $attempts = ClientTrackAction::query()->whereNotNull('save')->count();
        $recovered = ClientButtonTrack::query()->where('button_text', 'Recovered')->count();

        $windows = ClientTrackAction::query()
            ->whereNotNull('save')
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('save')
            ->map(function ($ts) {
                try {
                    return Carbon::parse($ts)->format('D H:00');
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5);

        return [
            'attempts' => $attempts,
            'recovered' => $recovered,
            'success_rate' => $attempts > 0 ? round($recovered / $attempts * 100, 1) : 0.0,
            'windows' => $windows,
        ];
    }

    public function render(RecoveryControlCenterSettings $settings): View
    {
        $data = ['tab' => $this->tab, 'liveAvailable' => $settings->liveModeAvailable()];

        $data += match ($this->tab) {
            'overview' => ['overview' => $this->overview()],
            'queue' => $this->queueData(),
            'activity' => ['activity' => $this->activityData()],
            'analytics' => ['analytics' => $this->analyticsData()],
            default => [],
        };

        return view('livewire.payment-failed.index', $data)
            ->layout('layouts.app', ['title' => 'Payment Failed']);
    }
}
