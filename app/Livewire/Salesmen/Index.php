<?php

namespace App\Livewire\Salesmen;

use App\Models\Role;
use App\Models\SaleCommission;
use App\Models\SalesmanPayroll;
use App\Models\User;
use App\Services\Commission\PayPeriodService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Team & Salesmen — the staff roster. Role tabs (All / Admin / Manager /
 * Salesman / CS Agent), search, one card per user with their 3CX + VanilaSoft
 * ids, customer count, total paid out and next-payday estimate. "Add Team
 * Member" creates a users row.
 */
class Index extends Component
{
    #[Url]
    public string $role = 'all'; // all | admin | manager | salesman | cs

    #[Url]
    public string $search = '';

    // Add Team Member modal
    public bool $showAdd = false;

    public string $tmFirst = '';

    public string $tmLast = '';

    public string $tmEmail = '';

    public string $tmContact = '';

    public string $tmRole = '';

    public function setRole(string $role): void
    {
        $this->role = in_array($role, ['all', 'admin', 'manager', 'salesman', 'cs'], true) ? $role : 'all';
    }

    public function addTeamMember(): void
    {
        $data = $this->validate([
            'tmFirst' => 'required|string|max:120',
            'tmLast' => 'nullable|string|max:120',
            'tmEmail' => 'required|email|max:190|unique:users,email',
            'tmContact' => 'nullable|string|max:40',
            'tmRole' => 'nullable',
        ]);

        User::create([
            'first_name' => $data['tmFirst'],
            'last_name' => $data['tmLast'],
            'email' => $data['tmEmail'],
            'contact' => $data['tmContact'],
            'role_id' => $data['tmRole'] !== '' ? (int) $data['tmRole'] : null,
            'type' => 1,
            'status' => 1,
            'password' => bcrypt(Str::random(24)),
        ]);

        $this->reset('showAdd', 'tmFirst', 'tmLast', 'tmEmail', 'tmContact', 'tmRole');
        Flux::toast(variant: 'success', text: __('Team member added.'));
    }

    /** admin | manager | salesman | cs */
    public static function roleKey(User $u): string
    {
        if ($u->role_id === null || $u->role_id === '') {
            return 'admin';
        }

        $name = Str::lower((string) ($u->role?->name ?? ''));

        return match (true) {
            str_contains($name, 'manager') => 'manager',
            str_contains($name, 'customer service') || str_contains($name, 'cs ') => 'cs',
            str_contains($name, 'salesman') || str_contains($name, 'sales') => 'salesman',
            default => 'salesman',
        };
    }

    public function render(PayPeriodService $periods): View
    {
        $period = $periods->current();

        $customerCounts = SaleCommission::query()
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(distinct client_id) as c')
            ->groupBy('user_id')->pluck('c', 'user_id');

        $nextPay = SaleCommission::query()
            ->whereNotNull('user_id')->where('is_cancel', 0)->where('is_paid', 0)
            ->whereBetween('store_date', [$period['start']->copy()->startOfDay(), $period['end']->copy()->endOfDay()])
            ->selectRaw('user_id, coalesce(sum(commision_price),0) as g')
            ->groupBy('user_id')->pluck('g', 'user_id');

        $paidOut = SalesmanPayroll::query()
            ->selectRaw('user_id, coalesce(sum(net),0) as n')
            ->groupBy('user_id')->pluck('n', 'user_id');

        $members = User::query()
            ->with('role:id,name')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'contact', 'role_id', 'type', 'status', 'ext_number', 'v_salesman_id'])
            ->map(function (User $u) use ($customerCounts, $nextPay, $paidOut) {
                $u->roleKey = self::roleKey($u);
                $u->customerCount = (int) ($customerCounts[$u->id] ?? 0);
                $u->nextPayEst = (float) ($nextPay[$u->id] ?? 0);
                $u->totalPaidOut = (float) ($paidOut[$u->id] ?? 0);

                return $u;
            })
            ->when($this->role !== 'all', fn ($c) => $c->where('roleKey', $this->role))
            ->values();

        $grouped = $members->groupBy('roleKey');
        $order = ['admin' => 'Admins', 'manager' => 'Managers', 'salesman' => 'Salesmen', 'cs' => 'CS Agents'];

        return view('livewire.salesmen.index', [
            'members' => $members,
            'grouped' => collect($order)->mapWithKeys(fn ($label, $key) => [$key => [
                'label' => $label,
                'items' => $grouped->get($key, collect()),
            ]])->filter(fn ($g) => $g['items']->isNotEmpty()),
            'totalCount' => User::count(),
            'roles' => Role::orderBy('name')->pluck('name', 'id'),
            'nextPayday' => $period['payday'],
        ])->layout('layouts.app', ['title' => 'Team & Salesmen']);
    }
}
