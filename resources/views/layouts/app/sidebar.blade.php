@php
    $groups = [
        ['heading' => __('Overview'), 'items' => [
            ['label' => __('Dashboard'), 'icon' => 'squares-2x2', 'route' => 'dashboard', 'pattern' => 'dashboard'],
        ]],
        ['heading' => __('Customers'), 'items' => [
            ['label' => __('Customers'), 'icon' => 'users', 'route' => 'customers.index', 'pattern' => 'customers.*'],
            ['label' => __('Sale Sheet'), 'icon' => 'clipboard-document-list', 'route' => 'sale-sheet.index', 'pattern' => 'sale-sheet.*'],
            ['label' => __('Leads'), 'icon' => 'funnel', 'route' => 'leads.index', 'pattern' => 'leads.*'],
            ['label' => __('Search'), 'icon' => 'magnifying-glass', 'route' => 'search.index', 'pattern' => 'search.*'],
        ]],
        ['heading' => __('Sales Team'), 'items' => [
            ['label' => __('Salesmen'), 'icon' => 'user-group', 'route' => 'salesmen.index', 'pattern' => 'salesmen.*'],
            ['label' => __('Commissions'), 'icon' => 'currency-dollar', 'route' => 'commissions.index', 'pattern' => 'commissions.*'],
            ['label' => __('Earnings'), 'icon' => 'banknotes', 'route' => 'earnings.index', 'pattern' => 'earnings.*'],
            ['label' => __('Salesman Messages'), 'icon' => 'chat-bubble-left-right', 'route' => 'salesman-messages.index', 'pattern' => 'salesman-messages.*', 'super' => true],
        ]],
        ['heading' => __('Operations'), 'items' => [
            ['label' => __('Blast Hub'), 'icon' => 'megaphone', 'route' => 'blast-hub.index', 'pattern' => 'blast-hub.*'],
            ['label' => __('Entrance Blast'), 'icon' => 'paper-airplane', 'route' => 'entrance-blast.index', 'pattern' => 'entrance-blast.*'],
            ['label' => __('Payment Failed'), 'icon' => 'exclamation-triangle', 'route' => 'payment-failed.index', 'pattern' => 'payment-failed.*'],
            ['label' => __('Renewals'), 'icon' => 'arrow-path', 'route' => 'renewals.index', 'pattern' => 'renewals.*'],
            ['label' => __('Activity'), 'icon' => 'clock', 'route' => 'activity.index', 'pattern' => 'activity.*'],
            ['label' => __('Board History'), 'icon' => 'book-open', 'route' => 'board-history.index', 'pattern' => 'board-history.*'],
            ['label' => __('Disputes'), 'icon' => 'scale', 'route' => 'disputes.index', 'pattern' => 'disputes.*'],
            ['label' => __('Financials'), 'icon' => 'chart-bar-square', 'route' => 'financials.index', 'pattern' => 'financials.*'],
            ['label' => __('Payment Health'), 'icon' => 'heart', 'route' => 'payment-health.index', 'pattern' => 'payment-health.*'],
        ]],
        ['heading' => __('Service'), 'items' => [
            ['label' => __('Claims'), 'icon' => 'wrench-screwdriver', 'route' => 'claims.index', 'pattern' => 'claims.*'],
            ['label' => __('Technicians'), 'icon' => 'identification', 'route' => 'technicians.index', 'pattern' => 'technicians.*'],
        ]],
        ['heading' => __('Billing'), 'items' => [
            ['label' => __('Control Center'), 'icon' => 'adjustments-horizontal', 'route' => 'billing.settings', 'pattern' => 'billing.settings'],
            ['label' => __('Merchant Accounts'), 'icon' => 'credit-card', 'route' => 'billing.accounts', 'pattern' => 'billing.accounts'],
            ['label' => __('Webhook Events'), 'icon' => 'bolt', 'route' => 'billing.webhook-events', 'pattern' => 'billing.webhook-events'],
            ['label' => __('Failed Payments'), 'icon' => 'exclamation-circle', 'route' => 'failed-payments.index', 'pattern' => 'failed-payments.*'],
        ]],
        ['heading' => __('Reports'), 'items' => [
            ['label' => __('Finance Cockpit'), 'icon' => 'chart-bar', 'route' => 'reports.finance-cockpit', 'pattern' => 'reports.finance-cockpit'],
            ['label' => __('Gateway Monitor'), 'icon' => 'signal', 'route' => 'reports.gateway-monitor', 'pattern' => 'reports.gateway-monitor'],
            ['label' => __('ANet Recurring Health'), 'icon' => 'heart', 'route' => 'reports.anet-health', 'pattern' => 'reports.anet-health'],
            ['label' => __('Webhook Report'), 'icon' => 'bolt', 'route' => 'reports.webhook-report', 'pattern' => 'reports.webhook-report'],
            ['label' => __('Lead Automation'), 'icon' => 'sparkles', 'route' => 'automation.leads', 'pattern' => 'automation.leads'],
        ]],
        ['heading' => __('System'), 'items' => [
            ['label' => __('Settings'), 'icon' => 'cog-6-tooth', 'route' => 'settings.hub', 'pattern' => 'settings.hub'],
        ]],
    ];

    $isSuper = (bool) auth()->user()?->isSuperAdmin();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body
    class="min-h-screen bg-[var(--color-canvas)] text-zinc-800 antialiased dark:text-zinc-100"
    x-data="{
        collapsed: localStorage.getItem('ahc_collapsed') === '1',
        mobileOpen: false,
        toggle() { this.collapsed = ! this.collapsed; localStorage.setItem('ahc_collapsed', this.collapsed ? '1' : '0'); },
    }"
>
    {{-- Mobile backdrop --}}
    <div x-show="mobileOpen" x-transition.opacity @click="mobileOpen = false"
         class="fixed inset-0 z-30 bg-black/40 lg:hidden" style="display:none"></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-[var(--color-navy-900)] text-white transition-all duration-200 lg:translate-x-0"
        x-bind:class="{
            '-translate-x-full': ! mobileOpen,
            'translate-x-0': mobileOpen,
            'lg:w-[68px]': collapsed,
            'lg:w-64': ! collapsed,
        }"
    >
        <div class="flex h-16 shrink-0 items-center gap-3 px-4">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-[var(--color-brand-600)]">
                <x-app-logo-icon class="size-5 fill-current text-white" />
            </span>
            <span x-show="! collapsed" class="leading-tight">
                <span class="block text-sm font-bold">American Home</span>
                <span class="block text-xs text-blue-300/80">{{ __('Coverage CRM') }}</span>
            </span>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-4">
            @foreach ($groups as $group)
                @php($items = collect($group['items'])->filter(fn ($i) => (! ($i['super'] ?? false) || $isSuper) && \Illuminate\Support\Facades\Route::has($i['route'])))
                @if ($items->isNotEmpty())
                    <div>
                        <div x-show="! collapsed" class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-white/35">
                            {{ $group['heading'] }}
                        </div>
                        <div class="space-y-0.5">
                            @foreach ($items as $item)
                                @php($active = request()->routeIs($item['pattern']))
                                <a href="{{ route($item['route']) }}" wire:navigate @click="mobileOpen = false"
                                   @class([
                                       'group flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition',
                                       'bg-[var(--color-brand-600)] text-white shadow-sm' => $active,
                                       'text-white/70 hover:bg-white/5 hover:text-white' => ! $active,
                                   ])
                                   x-bind:class="collapsed && 'lg:justify-center'">
                                    <flux:icon :name="$item['icon']" variant="outline" class="size-5 shrink-0" />
                                    <span x-show="! collapsed" class="truncate">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="shrink-0 border-t border-white/10 p-3">
            <a href="{{ route('profile.edit') }}" wire:navigate
               class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-white/5">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[var(--color-brand-600)] text-sm font-bold">
                    {{ auth()->user()->initials() }}
                </span>
                <span x-show="! collapsed" class="leading-tight">
                    <span class="block text-sm font-semibold">{{ auth()->user()->name }}</span>
                    <span class="block text-xs text-white/50">{{ $isSuper ? __('Super Admin') : __('Admin') }}</span>
                </span>
            </a>
            <button type="button" @click="toggle()"
                    class="mt-1 hidden w-full items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-white/50 transition hover:bg-white/5 hover:text-white lg:flex">
                <flux:icon name="chevron-double-left" variant="outline" class="size-5 shrink-0 transition" x-bind:class="collapsed && 'rotate-180'" />
                <span x-show="! collapsed">{{ __('Collapse') }}</span>
            </button>
        </div>
    </aside>

    {{-- Main column --}}
    <div class="flex min-h-screen flex-col transition-all duration-200" x-bind:class="collapsed ? 'lg:pl-[68px]' : 'lg:pl-64'">
        <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b border-[var(--color-hairline)] bg-[var(--color-surface)]/85 px-4 backdrop-blur">
            <button type="button" @click="mobileOpen = true"
                    class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100 lg:hidden dark:hover:bg-zinc-800">
                <flux:icon name="bars-3" variant="outline" class="size-5" />
            </button>

            <div class="flex-1"></div>

            <button type="button" x-data
                    @click="$flux.appearance = $flux.appearance === 'dark' ? 'light' : 'dark'"
                    class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    aria-label="{{ __('Toggle theme') }}">
                <flux:icon name="moon" variant="outline" class="size-5 dark:hidden" />
                <flux:icon name="sun" variant="outline" class="hidden size-5 dark:block" />
            </button>

            <button type="button" class="rounded-md p-2 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('Notifications') }}">
                <flux:icon name="bell" variant="outline" class="size-5" />
            </button>

            <flux:dropdown position="bottom" align="end">
                <button type="button" class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <span class="grid size-8 place-items-center rounded-full bg-[var(--color-brand-600)] text-xs font-bold text-white">
                        {{ auth()->user()->initials() }}
                    </span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-sm font-semibold">{{ auth()->user()->name }}</span>
                        <span class="block text-xs text-zinc-500">{{ $isSuper ? __('Super Admin') : __('Admin') }}</span>
                    </span>
                    <flux:icon name="chevron-down" variant="outline" class="size-4 text-zinc-400" />
                </button>

                <flux:menu>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </header>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>
</html>
