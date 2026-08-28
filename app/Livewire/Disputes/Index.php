<?php

namespace App\Livewire\Disputes;

use App\Models\PaymentHistory;
use App\Services\Billing\DisputeService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Disputes — chargeback / dispute management. The live data comes from
 * disputed payment_histories rows (disputed = 1) worked through DisputeService.
 * An external "Zen Payments" chargeback feed can be wired in later via the
 * webhook shown on the page; until ZEN_API_KEY_* is set the Zen tab stays a
 * connect-prompt and "Sync from Zen" is a no-op.
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'customers'; // customers | zen

    #[Url]
    public string $search = '';

    #[Url]
    public string $state = 'open'; // open | won | lost | all

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'state', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function refresh(): void
    {
        Flux::toast(text: __('Refreshed.'));
    }

    public function syncFromZen(): void
    {
        Flux::toast(
            variant: 'warning',
            text: __('Zen Payments is not connected — set ZEN_API_KEY_1 / ZEN_API_KEY_2 in the environment to enable sync.'),
        );
    }

    public function zenConnected(): bool
    {
        return filled(config('services.zen.key_1')) || filled(config('services.zen.key_2'));
    }

    public function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/zen/webhook';
    }

    protected function query(): Builder
    {
        return PaymentHistory::query()
            ->where('disputed', 1)
            ->with('userInfo:id,first_name,last_name,policy_number,email')
            ->when($this->search !== '', fn (Builder $q) => $q->whereHas('userInfo', fn (Builder $c) => $c->search($this->search)))
            ->when($this->state === 'open', fn (Builder $q) => $q->where('win', 0)->where('loss', 0))
            ->when($this->state === 'won', fn (Builder $q) => $q->where('win', 1))
            ->when($this->state === 'lost', fn (Builder $q) => $q->where('loss', 1));
    }

    public function resolve(DisputeService $disputes, int $paymentId, string $outcome): void
    {
        abort_unless(in_array($outcome, ['win', 'loss'], true), 422);

        $disputes->resolveDispute(PaymentHistory::findOrFail($paymentId), $outcome);
        Flux::toast(variant: 'success', text: __('Dispute marked :outcome.', ['outcome' => $outcome]));
    }

    public function reset_(DisputeService $disputes, int $paymentId): void
    {
        $disputes->resetDispute(PaymentHistory::findOrFail($paymentId));
        Flux::toast(variant: 'success', text: __('Dispute cleared.'));
    }

    public function render(): View
    {
        return view('livewire.disputes.index', [
            'rows' => $this->tab === 'customers' ? $this->query()->latest('id')->paginate(30) : null,
            'zenConnected' => $this->zenConnected(),
            'webhookUrl' => $this->webhookUrl(),
            'totals' => [
                'open' => (int) PaymentHistory::where('disputed', 1)->where('win', 0)->where('loss', 0)->count(),
                'at_risk' => (float) PaymentHistory::where('disputed', 1)->where('win', 0)->where('loss', 0)->sum('amount'),
                'lost' => (float) PaymentHistory::where('disputed', 1)->where('loss', 1)->sum('amount'),
                'zen_records' => 0,
            ],
        ])->layout('layouts.app', ['title' => 'Disputes']);
    }
}
