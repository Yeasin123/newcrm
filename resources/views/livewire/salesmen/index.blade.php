@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $roleBadge = [
        'admin' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
        'manager' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
        'salesman' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'cs' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    ];
    $roleLabel = ['admin' => 'Admin', 'manager' => 'Manager', 'salesman' => 'Salesman', 'cs' => 'CS Agent'];
    $avatarTones = ['bg-blue-600', 'bg-emerald-600', 'bg-violet-600', 'bg-amber-500', 'bg-rose-500', 'bg-teal-600'];
@endphp

<div class="w-full">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Team & Salesmen') }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ trans_choice(':count team member|:count team members', $totalCount, ['count' => $totalCount]) }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('commissions.index') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3.5 py-2 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                <flux:icon name="arrow-down-tray" variant="micro" class="size-4" /> {{ __('Commission Report') }}
            </a>
            <button type="button" wire:click="$set('showAdd', true)" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <flux:icon name="plus" variant="micro" class="size-4" /> {{ __('Add Team Member') }}
            </button>
        </div>
    </div>

    {{-- Role tabs + search --}}
    <div class="mt-5 flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap gap-1 border-b border-[var(--color-hairline)] text-sm">
            @foreach (['all' => __('All'), 'admin' => __('Admin'), 'manager' => __('Manager'), 'salesman' => __('Salesman'), 'cs' => __('CS Agent')] as $key => $label)
                <button type="button" wire:click="setRole('{{ $key }}')"
                    @class([
                        '-mb-px border-b-2 px-3 py-2.5 font-medium transition',
                        'border-blue-600 text-blue-600' => $role === $key,
                        'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $role !== $key,
                    ])>{{ $label }}</button>
            @endforeach
        </div>
        <div class="relative max-w-xs flex-1">
            <flux:icon name="magnifying-glass" variant="outline" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name or email…') }}"
                class="w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] py-2 pl-9 pr-3 text-sm placeholder:text-zinc-400 focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
        </div>
    </div>

    {{-- Cards --}}
    @php($sections = $role === 'all' ? $grouped : collect(['x' => ['label' => null, 'items' => $members]]))
    @forelse ($sections as $section)
        @if ($section['label'])
            <div class="mt-6 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-zinc-500">
                <span class="size-2 rounded-full bg-emerald-500"></span> {{ $section['label'] }} <span class="text-zinc-400">{{ $section['items']->count() }}</span>
            </div>
        @endif

        <div class="mt-3 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @foreach ($section['items'] as $m)
                @php($rk = $m->roleKey)
                <div wire:key="member-{{ $m->id }}" class="{{ $card }} flex flex-col">
                    <div class="flex items-start gap-3">
                        <div class="grid size-11 shrink-0 place-items-center rounded-xl {{ $avatarTones[$m->id % count($avatarTones)] }} text-sm font-bold text-white">{{ $m->initials() }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-bold text-[var(--color-navy-900)] dark:text-white">{{ $m->name }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $roleBadge[$rk] }}">{{ $roleLabel[$rk] }}</span>
                            </div>
                            <div class="truncate text-xs text-zinc-500">{{ $m->email }}</div>
                            @if ($m->contact)<a href="tel:{{ $m->contact }}" class="text-xs text-blue-600 underline decoration-blue-600/30">{{ $m->contact }}</a>@endif
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <span title="{{ __('Active') }}" class="{{ $m->status ? 'text-emerald-500' : 'text-zinc-300' }}"><flux:icon name="check-circle" variant="{{ $m->status ? 'solid' : 'outline' }}" class="size-4" /></span>
                        </div>
                    </div>

                    @if ($m->ext_number || $m->v_salesman_id)
                        <div class="mt-2 flex flex-wrap gap-x-4 text-[11px] text-zinc-400">
                            @if ($m->ext_number)<span><span class="font-semibold text-zinc-500">3CX:</span> {{ $m->ext_number }}</span>@endif
                            @if ($m->v_salesman_id)<span><span class="font-semibold text-zinc-500">VS ID:</span> <span class="underline">{{ $m->v_salesman_id }}</span></span>@endif
                        </div>
                    @endif

                    <div class="mt-3 flex-1">
                        <div class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ number_format($m->customerCount) }}</div>
                        <div class="text-xs text-zinc-500">{{ __('Customers') }}</div>
                    </div>

                    @if (in_array($rk, ['admin', 'manager', 'salesman'], true))
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-[var(--color-hairline)] pt-3 text-xs">
                            <span class="inline-flex items-center gap-1 text-zinc-400"><flux:icon name="arrow-trending-up" variant="micro" class="size-3.5" /> {{ __('Total Paid Out') }} <span class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">${{ number_format($m->totalPaidOut, 2) }}</span></span>
                            <span class="inline-flex items-center gap-1 text-zinc-400"><flux:icon name="clock" variant="micro" class="size-3.5" /> {{ __('Next Pay') }} {{ $nextPayday->format('M j, Y') }} <span class="font-semibold text-amber-500">${{ number_format($m->nextPayEst, 2) }}</span></span>
                            <a href="{{ route('salesmen.show', $m) }}" wire:navigate class="inline-flex items-center gap-1 font-semibold text-blue-600 hover:underline">{{ __('View') }} <flux:icon name="chevron-right" variant="micro" class="size-3.5" /></a>
                        </div>
                    @else
                        <div class="mt-4 border-t border-[var(--color-hairline)] pt-3 text-right text-xs">
                            <a href="{{ route('salesmen.show', $m) }}" wire:navigate class="inline-flex items-center gap-1 font-semibold text-blue-600 hover:underline">{{ __('View') }} <flux:icon name="chevron-right" variant="micro" class="size-3.5" /></a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <div class="{{ $card }} mt-6 py-16 text-center text-sm text-zinc-500">{{ __('No team members match.') }}</div>
    @endforelse

    {{-- Add Team Member modal --}}
    <flux:modal wire:model="showAdd" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Add Team Member') }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="tmFirst" :label="__('First Name')" />
                <flux:input wire:model="tmLast" :label="__('Last Name')" />
                <flux:input wire:model="tmEmail" type="email" :label="__('Email')" class="sm:col-span-2" />
                <flux:input wire:model="tmContact" :label="__('Phone')" />
                <flux:select wire:model="tmRole" :label="__('Role')">
                    <flux:select.option value="">{{ __('Admin (no role)') }}</flux:select.option>
                    @foreach ($roles as $id => $name)
                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            @error('tmEmail') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            @error('tmFirst') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            <p class="text-xs text-zinc-400">{{ __('A random password is set — the member resets it from the login page.') }}</p>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showAdd', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="addTeamMember">{{ __('Add') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
