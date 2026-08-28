<?php

namespace App\Livewire\FailedPayments;

use App\Enums\FailedPaymentCaseStatus;
use App\Models\FailedPaymentCase;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Failed-payment case list. Ported from the legacy FailedPaymentController@index
 * — same filter set (search / status / frequency / category / assignment /
 * retry status / date range) and the same dashboard metric tiles.
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $frequency = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $assignment = 'all';

    #[Url]
    public ?int $assigned_user_id = null;

    #[Url]
    public string $retry_status = '';

    #[Url]
    public string $from_date = '';

    #[Url]
    public string $to_date = '';

    #[Url]
    public int $per_page = 20;

    public string $retryOutput = '';

    public function mount(): void
    {
        abort_unless($this->moduleEnabled(), 404);
        Gate::authorize('viewAny', FailedPaymentCase::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'frequency', 'category', 'assignment', 'assigned_user_id', 'retry_status', 'from_date', 'to_date', 'per_page'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'frequency', 'category', 'retry_status', 'from_date', 'to_date', 'assigned_user_id']);
        $this->assignment = 'all';
        $this->resetPage();
    }

    /** Run the retry cron on demand (permission: failed-payments.retry-control). */
    public function runRetries(): void
    {
        abort_unless(auth()->user()?->hasPermission('failed-payments.retry-control'), 403);

        $exit = Artisan::call('failed-payments:process-retries', ['--limit' => 25]);
        $this->retryOutput = trim((string) Artisan::output());

        Flux::toast(
            variant: $exit === 0 ? 'success' : 'warning',
            text: $exit === 0
                ? __('Retry job executed.')
                : __('Retry job finished with warnings — see the output below.'),
        );
    }

    protected function moduleEnabled(): bool
    {
        return (bool) config('failed_payments.module_enabled') && (bool) config('failed_payments.admin_enabled');
    }

    protected function query(): Builder
    {
        $from = $this->dateOnly($this->from_date);
        $to = $this->dateOnly($this->to_date);

        return FailedPaymentCase::query()
            ->with([
                'client:id,first_name,last_name,email,policy_number,status,authorize_status',
                'paymentHistory:id,client_id,subscription_id,transaction_id,amount,payment_date,payment_status,authorize_account_id,currency',
                'authorizeAccount:id,name,environment,is_active',
                'assignedUser:id,first_name,last_name,email,role_id',
            ])
            ->withCount(['notes', 'auditLogs', 'communications', 'webhookEvents', 'retrySchedules'])
            ->when($this->search !== '', function (Builder $query) {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term) {
                    $inner->where('case_uuid', 'like', $term)
                        ->orWhere('case_key', 'like', $term)
                        ->orWhere('arb_subscription_id', 'like', $term)
                        ->orWhere('anet_transaction_id', 'like', $term)
                        ->orWhere('failure_reason', 'like', $term)
                        ->orWhere('failure_category', 'like', $term)
                        ->orWhereHas('client', function (Builder $c) use ($term) {
                            $c->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('email', 'like', $term)
                                ->orWhere('policy_number', 'like', $term);
                        });
                });
            })
            ->when($this->status !== '', fn (Builder $q) => $q->where('current_status', $this->status))
            ->when($this->frequency !== '', fn (Builder $q) => $q->where('payment_frequency', $this->frequency))
            ->when($this->category !== '', fn (Builder $q) => $q->where('failure_category', 'like', '%'.$this->category.'%'))
            ->when($this->assignment === 'assigned', fn (Builder $q) => $q->whereNotNull('assigned_user_id'))
            ->when($this->assignment === 'unassigned', fn (Builder $q) => $q->whereNull('assigned_user_id'))
            ->when(! empty($this->assigned_user_id), fn (Builder $q) => $q->where('assigned_user_id', $this->assigned_user_id))
            ->when($this->retry_status !== '', fn (Builder $q) => $q->whereHas('retrySchedules', fn (Builder $r) => $r->where('status', $this->retry_status)))
            ->when($from !== null, fn (Builder $q) => $q->whereDate('latest_failed_at', '>=', $from->format('Y-m-d')))
            ->when($to !== null, fn (Builder $q) => $q->whereDate('latest_failed_at', '<=', $to->format('Y-m-d')))
            ->orderByDesc('latest_failed_at')
            ->orderByDesc('id');
    }

    protected function metrics(): array
    {
        $base = FailedPaymentCase::query();
        $total = (clone $base)->count();
        $countFor = fn (string $s) => (clone $base)->where('current_status', $s)->count();
        $recovered = $countFor(FailedPaymentCaseStatus::RECOVERED);
        $resolved = $countFor(FailedPaymentCaseStatus::RESOLVED);
        $cancelled = $countFor(FailedPaymentCaseStatus::CANCELLED);

        return [
            'total' => $total,
            'open' => $countFor(FailedPaymentCaseStatus::OPEN),
            'new' => $countFor(FailedPaymentCaseStatus::NEW),
            'unmatched' => $countFor(FailedPaymentCaseStatus::UNMATCHED),
            'under_review' => $countFor(FailedPaymentCaseStatus::UNDER_REVIEW),
            'recovered' => $recovered,
            'resolved' => $resolved,
            'cancelled' => $cancelled,
            'recovery_rate' => $total > 0 ? round(($recovered / $total) * 100, 1) : 0.0,
        ];
    }

    protected function dateOnly(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.failed-payments.index', [
            'cases' => $this->query()->paginate($this->per_page),
            'metrics' => $this->metrics(),
            'statuses' => FailedPaymentCaseStatus::all(),
            'assignmentUsers' => User::query()
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->orderBy('first_name')->orderBy('last_name')->get(),
            'canRetry' => (bool) auth()->user()?->hasPermission('failed-payments.retry-control'),
        ])->layout('layouts.app', ['title' => 'Failed Payments']);
    }
}
