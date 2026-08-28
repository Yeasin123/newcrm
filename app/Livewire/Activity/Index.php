<?php

namespace App\Livewire\Activity;

use App\Models\TrackSalesmanAction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Salesman action feed, built on `track_salesman_actions` (the table that is
 * actually populated) — each row is one lead touched, with per-action
 * timestamp-string columns.
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $salesman = '';

    #[Url]
    public string $action = ''; // one of TrackSalesmanAction::ACTIONS

    #[Url]
    public string $date = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['salesman', 'action', 'date'], true)) {
            $this->resetPage();
        }
    }

    protected function query(): Builder
    {
        return TrackSalesmanAction::query()
            ->with('salesman:id,first_name,last_name')
            ->when($this->salesman !== '', fn (Builder $q) => $q->where('user_id', $this->salesman))
            ->when(in_array($this->action, TrackSalesmanAction::ACTIONS, true),
                fn (Builder $q) => $q->whereNotNull($this->action)->where($this->action, '!=', ''))
            ->when($this->date !== '', fn (Builder $q) => $q->whereDate('STORE_DATE', $this->date));
    }

    public function render(): View
    {
        return view('livewire.activity.index', [
            'rows' => $this->query()->orderByDesc('id')->paginate(40),
            'salesmen' => User::query()->where('type', 1)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'actions' => TrackSalesmanAction::ACTIONS,
        ])->layout('layouts.app', ['title' => 'Activity']);
    }
}
