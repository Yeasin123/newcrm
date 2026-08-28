<?php

namespace App\Livewire\Customers;

use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Customers list — tabbed segment filters with live counts, search, and
 * infinite scroll (no numbered pages: the row limit grows as you reach the
 * bottom, capped so an unfiltered scroll can't runaway).
 */
class Index extends Component
{
    private const PAGE = 30;

    private const MAX_ROWS = 600;

    /** Tab key => label. A subset of Client::SEGMENTS, in display order. */
    public const TABS = [
        'all' => 'All',
        'active' => 'Active',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'unsuccessful' => 'Failed',
        'bypass' => 'Bypass',
        'renewal-today' => 'Renewal',
        'canceled' => 'Cancelled',
        'disputed' => 'Disputed',
    ];

    #[Url]
    public string $segment = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public string $salesman = '';

    #[Url]
    public string $sort = 'id';

    #[Url]
    public string $direction = 'desc';

    public int $perPage = self::PAGE;

    public function updated(string $property): void
    {
        if (in_array($property, ['segment', 'search', 'salesman'], true)) {
            $this->perPage = self::PAGE;
        }
    }

    public function setSegment(string $segment): void
    {
        $this->segment = array_key_exists($segment, self::TABS) ? $segment : 'all';
        $this->perPage = self::PAGE;
    }

    public function loadMore(): void
    {
        $this->perPage = min($this->perPage + self::PAGE, self::MAX_ROWS);
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = $column === 'created_at' || $column === 'id' ? 'desc' : 'asc';
    }

    /** @return array<string, mixed> filter state for the CSV export link */
    public function getExportParamsProperty(): array
    {
        return array_filter([
            'segment' => $this->segment,
            'search' => $this->search,
            'salesman' => $this->salesman,
        ]);
    }

    /** @return list<string> */
    public function salesmen(): array
    {
        return Client::query()
            ->whereNotNull('salesman_name')
            ->where('salesman_name', '!=', '')
            ->distinct()
            ->orderBy('salesman_name')
            ->pluck('salesman_name')
            ->all();
    }

    private function baseQuery(): Builder
    {
        return Client::query()
            ->search($this->search)
            ->when($this->salesman !== '', fn (Builder $q) => $q->where('salesman_name', $this->salesman));
    }

    /** @return array<string, int> */
    private function tabCounts(): array
    {
        $counts = [];
        foreach (array_keys(self::TABS) as $key) {
            $counts[$key] = (clone $this->baseQuery())->segment($key)->toBase()->getCountForPagination();
        }

        return $counts;
    }

    public function render(): View
    {
        $sortable = ['id', 'first_name', 'email', 'policy_number', 'status', 'created_at', 'start_date', 'end_date'];
        $sort = in_array($this->sort, $sortable, true) ? $this->sort : 'id';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        $query = $this->baseQuery()
            ->segment($this->segment)
            ->with(['propertyPackage:id,title', 'latestEligiblePolicyPaymentHistory']);

        $total = (clone $query)->toBase()->getCountForPagination();

        $customers = $query->orderBy($sort, $direction)->limit($this->perPage)->get();

        return view('livewire.customers.index', [
            'customers' => $customers,
            'tabs' => self::TABS,
            'counts' => $this->tabCounts(),
            'totalCount' => $total,
            'hasMore' => $total > $this->perPage,
            'atCap' => $this->perPage >= self::MAX_ROWS && $total > self::MAX_ROWS,
        ])->layout('layouts.app', ['title' => 'Customers']);
    }
}
