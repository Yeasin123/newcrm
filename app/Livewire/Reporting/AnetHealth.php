<?php

namespace App\Livewire\Reporting;

use App\Models\Client;
use App\Models\PaymentHistory;
use App\Services\Billing\AnetPaymentHistoryResolver;
use App\Services\Reporting\AnetReportService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ANet Recurring Health. Ported from AuthorizeNetReportController (index +
 * paymentFailedMatch). Shows per-merchant-account local counts, and the latest
 * subscription payment row per (subscription, account) with a local↔gateway
 * mismatch verdict (live gateway checks are opt-in via
 * config('reporting.anet_live_checks')). One-click "backfill" fills any
 * missing monthly payment rows for a client via the Phase 6 resolver.
 */
class AnetHealth extends Component
{
    use WithPagination;

    #[Url]
    public ?int $authorize_account_id = null;

    #[Url]
    public string $local_status = 'failed'; // all | failed | paid

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('Settings'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['authorize_account_id', 'local_status', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function backfill(int $clientId, AnetPaymentHistoryResolver $resolver): void
    {
        abort_unless(auth()->user()?->hasPermission('Settings'), 403);

        $client = Client::find($clientId);
        abort_unless($client, 404);

        // Anchor on the client's most recent recurring policy payment.
        $last = PaymentHistory::query()
            ->where('client_id', $client->id)
            ->where('claim_payment', 0)->where('is_custom', 0)
            ->whereNotNull('subscription_id')->where('subscription_id', '!=', '')
            ->whereNotNull('payment_date')
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->first();

        if (! $last) {
            Flux::toast(variant: 'secondary', text: __('No recurring payment row to anchor a backfill on.'));

            return;
        }

        $before = PaymentHistory::where('client_id', $client->id)->count();

        $resolver->createMissingMonthlyPayments(
            $client,
            $last,
            Carbon::now(),
            (float) $last->amount,
            $last->authorize_account_id,
        );

        $created = PaymentHistory::where('client_id', $client->id)->count() - $before;

        Flux::toast(
            variant: $created > 0 ? 'success' : 'secondary',
            text: $created > 0
                ? __(':n missing monthly payment row(s) created.', ['n' => $created])
                : __('No missing monthly payment rows found.'),
        );
    }

    protected function query(): Builder
    {
        $latest = PaymentHistory::query()
            ->selectRaw('MAX(id) as id')
            ->whereNotNull('subscription_id')->where('subscription_id', '!=', '')
            ->where('claim_payment', 0)->where('is_custom', 0)
            ->groupBy('subscription_id', 'authorize_account_id');

        return PaymentHistory::query()
            ->joinSub($latest, 'latest', fn ($j) => $j->on('payment_histories.id', '=', 'latest.id'))
            ->with([
                'userInfo:id,first_name,last_name,email,status,authorize_status,authorize_account_id,policy_number,payment_faild_date',
                'authorizeAccount:id,name,environment,is_active',
            ])
            ->select('payment_histories.*')
            ->when($this->authorize_account_id, fn (Builder $q) => $q->where('payment_histories.authorize_account_id', $this->authorize_account_id))
            ->when($this->local_status === 'failed', fn (Builder $q) => $q->where('payment_histories.payment_status', 0))
            ->when($this->local_status === 'paid', fn (Builder $q) => $q->where('payment_histories.payment_status', '!=', 0))
            ->when($this->search !== '', function (Builder $q) {
                $t = '%'.$this->search.'%';
                $q->where(fn (Builder $i) => $i->where('payment_histories.subscription_id', 'like', $t)
                    ->orWhere('payment_histories.transaction_id', 'like', $t)
                    ->orWhereHas('userInfo', fn (Builder $c) => $c->where('first_name', 'like', $t)
                        ->orWhere('last_name', 'like', $t)->orWhere('email', 'like', $t)->orWhere('policy_number', 'like', $t)));
            })
            ->latest('payment_histories.created_at');
    }

    public function render(AnetReportService $svc): View
    {
        $summaries = $svc->accountSummaries();
        $rows = $this->query()->paginate(15);

        $rows->getCollection()->transform(function (PaymentHistory $p) use ($svc) {
            $check = $svc->checkSubscription($p);
            $p->live_check = $check;
            $p->match_status = $svc->buildMismatchStatus($p, $check);

            return $p;
        });

        return view('livewire.reporting.anet-health', [
            'summaries' => $summaries,
            'totals' => $svc->totals($summaries),
            'rows' => $rows,
            'accounts' => $summaries->pluck('account'),
            'liveChecksOn' => (bool) config('reporting.anet_live_checks'),
        ])->layout('layouts.app', ['title' => 'ANet Recurring Health']);
    }
}
