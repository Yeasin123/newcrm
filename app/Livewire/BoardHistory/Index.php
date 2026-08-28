<?php

namespace App\Livewire\BoardHistory;

use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Board History — every call-board interaction across agents, merged from the
 * Payment Failed board (client_button_tracks) and the Renewal board
 * (client_renew_button_tracks). Free-text button labels are normalised to a
 * fixed outcome set so the breakdown, leaderboard and filters line up.
 */
class Index extends Component
{
    use WithPagination;

    /** Recent-window cap — a history view never needs the whole table. */
    private const CAP = 5000;

    private const PER_PAGE = 25;

    public const OUTCOMES = [
        'payment_collected' => 'Payment Collected',
        'renewed' => 'Renewed',
        'upgraded' => 'Upgraded',
        'spoke_with' => 'Spoke With',
        'call_back' => 'Call Back',
        'no_answer' => 'No Answer',
        'dispute' => 'Dispute',
        'not_interested' => 'Not Interested',
        'skipped' => 'Skipped',
        'other' => 'Other',
    ];

    #[Url]
    public string $board = 'all'; // all | payment_failed | renewals

    #[Url]
    public string $outcome = 'all';

    #[Url]
    public string $agentScope = 'all'; // all | cs

    #[Url]
    public string $search = '';

    #[Url]
    public string $range = 'all'; // today | yesterday | week | month | all | custom

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['board', 'outcome', 'agentScope', 'search', 'range', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function setRange(string $range): void
    {
        $this->range = $range;
        $this->resetPage();
    }

    public static function classify(string $buttonText): string
    {
        $t = mb_strtolower(trim($buttonText));

        return match (true) {
            str_contains($t, 'recover'), str_contains($t, 'collect'), $t === 'paid' => 'payment_collected',
            str_contains($t, 'renew') => 'renewed',
            str_contains($t, 'upgrad') => 'upgraded',
            str_contains($t, 'spoke'), str_contains($t, 'contact') => 'spoke_with',
            str_contains($t, 'call back'), str_contains($t, 'callback') => 'call_back',
            str_contains($t, 'no answer') => 'no_answer',
            str_contains($t, 'disput') => 'dispute',
            str_contains($t, 'not interested') => 'not_interested',
            str_contains($t, 'skip') => 'skipped',
            default => 'other',
        };
    }

    /** Parse a legacy free-text board timestamp — the column mixes ISO and US formats. */
    public static function parseDate(?string $raw): ?Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'm-d-Y h:i A', 'm-d-Y H:i:s', 'm/d/Y h:i A', 'm/d/Y H:i:s', 'Y-m-d'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $raw);
                if ($d !== false) {
                    return $d;
                }
            } catch (\Throwable) {
                // try the next format
            }
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0:?Carbon,1:?Carbon} */
    private function window(): array
    {
        return match ($this->range) {
            'today' => [Carbon::today(), Carbon::today()->endOfDay()],
            'yesterday' => [Carbon::yesterday(), Carbon::yesterday()->endOfDay()],
            'week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'custom' => [
                $this->from !== '' ? Carbon::parse($this->from)->startOfDay() : null,
                $this->to !== '' ? Carbon::parse($this->to)->endOfDay() : null,
            ],
            default => [null, null],
        };
    }

    private function csAgentIds(): Collection
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'like', '%customer service%')->orWhere('name', 'like', '%cs%'))
            ->pluck('id');
    }

    /** All board rows matching board + date + agent-scope + search (outcome not applied). */
    private function scoped(): Collection
    {
        $pf = DB::table('client_button_tracks')
            ->selectRaw("id, client_id, user_id, create_date, button_text, comments, 'payment_failed' as board");
        $rn = DB::table('client_renew_button_tracks')
            ->selectRaw("id, client_id, user_id, create_date, button_text, comments, 'renewals' as board");

        $union = match ($this->board) {
            'payment_failed' => $pf,
            'renewals' => $rn,
            default => $pf->unionAll($rn),
        };

        $q = DB::query()->fromSub($union, 'bh');

        if ($this->agentScope === 'cs') {
            $q->whereIn('user_id', $this->csAgentIds());
        }

        if ($this->search !== '') {
            $term = trim($this->search);
            $clientIds = Client::query()->search($term)->limit(500)->pluck('id');
            $userIds = User::query()
                ->where(fn ($u) => $u->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                ->pluck('id');

            $q->where(function ($w) use ($term, $clientIds, $userIds) {
                $w->where('button_text', 'like', "%{$term}%")
                    ->orWhere('comments', 'like', "%{$term}%")
                    ->orWhereIn('client_id', $clientIds)
                    ->orWhereIn('user_id', $userIds);
            });
        }

        $raw = $q->orderByDesc('id')->limit(self::CAP)->get();

        $clients = Client::query()->whereIn('id', $raw->pluck('client_id')->unique()->filter())
            ->get(['id', 'first_name', 'last_name', 'policy_number'])->keyBy('id');
        $users = User::query()->whereIn('id', $raw->pluck('user_id')->unique()->filter())
            ->get(['id', 'first_name', 'last_name', 'email'])->keyBy('id');

        [$start, $end] = $this->window();

        return $raw->map(function ($r) use ($clients, $users) {
            $key = self::classify((string) $r->button_text);
            $at = self::parseDate($r->create_date);

            return (object) [
                'id' => $r->board.'-'.$r->id,
                'at' => $at,
                'at_label' => $at?->format('M j, Y') ?? '—',
                'at_time' => $at?->format('g:i A') ?? '',
                'client' => $clients->get($r->client_id),
                'client_id' => $r->client_id,
                'board_key' => $r->board,
                'board_label' => $r->board === 'renewals' ? __('Renewals') : __('Payment Failed'),
                'outcome' => $key,
                'outcome_label' => self::OUTCOMES[$key],
                'agent' => $users->get($r->user_id),
                'note' => $r->comments,
            ];
        })
            ->when($start, fn ($rows) => $rows->filter(fn ($r) => $r->at && $r->at->gte($start)))
            ->when($end, fn ($rows) => $rows->filter(fn ($r) => $r->at && $r->at->lte($end)))
            ->sortByDesc(fn ($r) => $r->at?->getTimestamp() ?? 0)
            ->values();
    }

    public function render(): View
    {
        $scoped = $this->scoped();
        $view = $this->outcome === 'all' || ! isset(self::OUTCOMES[$this->outcome])
            ? $scoped
            : $scoped->where('outcome', $this->outcome)->values();

        $total = $scoped->count();
        $collected = $scoped->where('outcome', 'payment_collected')->count();
        $noAnswer = $scoped->where('outcome', 'no_answer')->count();
        $agents = $scoped->filter(fn ($r) => $r->agent)->groupBy(fn ($r) => $r->agent->id);
        $topAgent = $agents->sortByDesc(fn ($g) => $g->count())->first();

        $breakdown = collect(self::OUTCOMES)->map(fn ($label, $key) => [
            'label' => $label,
            'key' => $key,
            'count' => $scoped->where('outcome', $key)->count(),
        ])->values();

        $leaderboard = $agents->map(fn ($g) => [
            'name' => $g->first()->agent->name,
            'calls' => $g->count(),
            'collected' => $g->where('outcome', 'payment_collected')->count(),
        ])->sortByDesc('calls')->take(8)->values();

        $page = max(1, (int) $this->getPage());
        $paginator = new LengthAwarePaginator(
            $view->forPage($page, self::PER_PAGE)->values(),
            $view->count(),
            self::PER_PAGE,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        return view('livewire.board-history.index', [
            'rows' => $paginator,
            'kpi' => [
                'total' => $total,
                'collected' => $collected,
                'success_rate' => $total > 0 ? round($collected / $total * 100) : 0,
                'no_answer' => $noAnswer,
                'no_answer_rate' => $total > 0 ? round($noAnswer / $total * 100) : 0,
                'agents' => $agents->count(),
                'top_agent' => $topAgent?->first()->agent->name,
            ],
            'breakdown' => $breakdown,
            'leaderboard' => $leaderboard,
            'cap' => self::CAP,
            'capped' => $scoped->count() >= self::CAP,
        ])->layout('layouts.app', ['title' => 'Board History']);
    }
}
