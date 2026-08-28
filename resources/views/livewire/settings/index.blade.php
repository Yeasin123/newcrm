@php($card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5')
@php($lbl = 'block text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100')
@php($inp = 'mt-1.5 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100')
@php($toggle = 'relative h-6 w-11 shrink-0 rounded-full bg-zinc-300 transition peer-checked:bg-blue-600 dark:bg-white/15 after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5')

<div class="flex w-full flex-col gap-6 lg:flex-row">
    {{-- Sub-nav --}}
    <aside class="lg:w-60 lg:shrink-0">
        @foreach ($sections as $group => $items)
            <div class="mb-4">
                <div class="px-2 text-[10px] font-bold uppercase tracking-wider text-zinc-400">{{ $group === 'admin' ? __('Admin') : __('Personal') }}</div>
                <nav class="mt-1 space-y-0.5">
                    @foreach ($items as $item)
                        <a href="{{ route('settings.hub', ['section' => $item['slug']]) }}" wire:navigate
                            @class([
                                'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition',
                                'bg-blue-600 text-white' => $section === $item['slug'],
                                'text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5' => $section !== $item['slug'],
                            ])>
                            <flux:icon name="{{ $item['icon'] }}" variant="micro" class="size-4" /> {{ __($item['label']) }}
                        </a>
                    @endforeach
                </nav>
            </div>
        @endforeach
    </aside>

    {{-- Panel --}}
    <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __($meta[1]) }}</h1>

        {{-- ============================ BRANDING ============================ --}}
        @if ($section === 'branding')
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Customize the look and feel of your CRM.') }}</p>
            <div class="{{ $card }} mt-5 max-w-2xl space-y-4">
                <div><label class="{{ $lbl }}">{{ __('Company Name') }}</label><input type="text" wire:model="branding.company_name" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">{{ __('Support Email') }}</label><input type="email" wire:model="branding.support_email" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">{{ __('Support Phone') }}</label><input type="text" wire:model="branding.support_phone" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">{{ __('Website URL') }}</label><input type="url" wire:model="branding.website_url" placeholder="https://…" class="{{ $inp }}"></div>
                @error('branding.support_email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @error('branding.website_url') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="saveBranding" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Save Branding') }}</button>
            </div>
        @endif

        {{-- ======================= SALE ANIMATIONS ======================= --}}
        @if ($section === 'animations')
            <div class="mt-0.5 flex items-start justify-between gap-4">
                <p class="text-sm text-zinc-500">{{ __('Choose which celebration animations play when a sale is closed. When multiple are selected, they rotate in round-robin order.') }}</p>
                <button type="button" wire:click="saveAnimations" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Save Changes') }}</button>
            </div>
            @foreach ($this->planTypes() as $planKey => $plan)
                @php($selected = $animations[$planKey] ?? [])
                <div class="{{ $card }} mt-4">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="rounded-full bg-black/5 px-2 py-0.5 text-[10px] font-bold uppercase text-zinc-500 dark:bg-white/10">{{ $plan[0] }}</span>
                        <h3 class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ $plan[1] }}</h3>
                        @if (count($selected) > 1)<span class="ml-auto rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ __('Round-robin (:n animations)', ['n' => count($selected)]) }}</span>@endif
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this->animationCatalog() as $key => $a)
                            @php($on = in_array($key, $selected, true))
                            <button type="button" wire:click="toggleAnimation('{{ $planKey }}', '{{ $key }}')" wire:key="anim-{{ $planKey }}-{{ $key }}"
                                @class([
                                    'flex items-start gap-2 rounded-xl border p-3 text-left transition',
                                    'border-blue-500 bg-blue-50 dark:bg-blue-500/10' => $on,
                                    'border-[var(--color-hairline)] hover:border-blue-300' => ! $on,
                                ])>
                                <span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded border {{ $on ? 'border-blue-600 bg-blue-600 text-white' : 'border-zinc-400' }}">@if($on)<flux:icon name="check" variant="micro" class="size-3" />@endif</span>
                                <span>
                                    <span class="block text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $a[0] }} {{ $a[1] }}</span>
                                    <span class="block text-xs text-zinc-500">{{ $a[2] }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <div class="mt-4 rounded-xl border border-violet-200 bg-violet-50 p-4 text-sm text-violet-800 dark:border-violet-500/25 dark:bg-violet-500/10 dark:text-violet-200">
                <span class="font-bold">{{ __('Round-Robin Rotation') }}</span>
                {{ __('When multiple animations are selected, they rotate in order with each new sale. A VIP 6-Year plan set to Slot Machine + Fireworks will alternate: sale 1 → Slot Machine, sale 2 → Fireworks, sale 3 → Slot Machine, etc.') }}
            </div>
        @endif

        {{-- ======================= PERMISSIONS & ROLES ======================= --}}
        @if ($section === 'permissions')
            <div class="mt-0.5 flex items-start justify-between gap-4">
                <p class="text-sm text-zinc-500">{{ __('Control what each role can see and do across the CRM. Changes apply immediately.') }}</p>
                <button type="button" wire:click="savePermissions" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Save Permissions') }}</button>
            </div>
            @foreach ($groups as $groupName => $perms)
                <div class="{{ $card }} mt-4 !p-0 overflow-hidden">
                    <div class="border-b border-[var(--color-hairline)] p-3 font-bold text-[var(--color-navy-900)] dark:text-white">{{ $groupName }}</div>
                    <table class="w-full text-sm">
                        <thead class="border-b border-[var(--color-hairline)]">
                            <tr class="[&>th]:px-4 [&>th]:py-2 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                                <th class="text-left">{{ __('Permission') }}</th>
                                <th class="text-center"><span class="rounded-full bg-blue-100 px-2 py-0.5 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">{{ __('Admin') }}</span></th>
                                <th class="text-center"><span class="rounded-full bg-violet-100 px-2 py-0.5 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ __('Manager') }}</span></th>
                                <th class="text-center"><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('Salesman') }}</span></th>
                                <th class="text-center"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ __('CS Agent') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-hairline)]">
                            @foreach ($perms as $perm)
                                <tr class="[&>td]:px-4 [&>td]:py-3">
                                    <td><span class="font-medium text-[var(--color-navy-900)] dark:text-zinc-100">{{ $perm }}</span></td>
                                    <td class="text-center"><span class="inline-block h-6 w-11 rounded-full bg-blue-600 opacity-60" title="{{ __('Always on for admins') }}"></span></td>
                                    @foreach (['manager', 'salesman', 'cs'] as $col)
                                        <td class="text-center">
                                            <label class="inline-flex cursor-pointer items-center">
                                                <input type="checkbox" wire:model="matrix.{{ $perm }}.{{ $col }}" class="peer sr-only">
                                                <span class="{{ $toggle }}"></span>
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif

        {{-- ======================= USER ACCOUNTS ======================= --}}
        @if ($section === 'users')
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('View all CRM users. Manage them from the Salesmen & Team section.') }}</p>
            <div class="{{ $card }} mt-5 !p-0 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="border-b border-[var(--color-hairline)] text-left">
                            <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                                <th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Role') }}</th><th>{{ __('Status') }}</th><th>{{ __('Joined') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-hairline)]">
                            @foreach ($users as $u)
                                <tr class="[&>td]:px-4 [&>td]:py-3">
                                    <td class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $u->name }}</td>
                                    <td class="text-zinc-500">{{ $u->email }}</td>
                                    <td><span class="rounded-full bg-black/5 px-2 py-0.5 text-xs font-semibold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ $u->role?->name ?? __('Admin') }}</span></td>
                                    <td><span @class(['rounded-full px-2 py-0.5 text-xs font-semibold', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $u->status, 'bg-zinc-200 text-zinc-500 dark:bg-white/10' => ! $u->status])>{{ $u->status ? __('Active') : __('Inactive') }}</span></td>
                                    <td class="whitespace-nowrap text-zinc-500">{{ optional($u->created_at)->format('Y-m-d') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ======================= COMMISSION RATES (embed) ======================= --}}
        @if ($section === 'commissions')
            <div class="mt-4">
                @livewire('commissions.settings')
            </div>
        @endif

        {{-- ============================ MY PROFILE ============================ --}}
        @if ($section === 'profile')
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Update your personal information and contact details.') }}</p>
            <div class="{{ $card }} mt-5 max-w-2xl space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="{{ $lbl }}">{{ __('First Name') }}</label><input type="text" wire:model="profile.first_name" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">{{ __('Last Name') }}</label><input type="text" wire:model="profile.last_name" class="{{ $inp }}"></div>
                </div>
                <div><label class="{{ $lbl }}">{{ __('Email') }}</label><input type="email" wire:model="profile.email" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">{{ __('Phone') }}</label><input type="text" wire:model="profile.contact" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}">{{ __('Change Password') }}</label><input type="password" wire:model="newPassword" placeholder="{{ __('Leave blank to keep current') }}" class="{{ $inp }}"></div>
                @error('profile.email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @error('newPassword') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="saveProfile" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Save Profile') }}</button>
            </div>
        @endif

        {{-- ============================ APPEARANCE ============================ --}}
        @if ($section === 'appearance')
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Choose your preferred color theme for the CRM.') }}</p>
            <div class="{{ $card }} mt-5 max-w-3xl" x-data>
                <div class="{{ $lbl }} mb-3">{{ __('Theme') }}</div>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ([['light', '☀️', 'Light'], ['dark', '🌙', 'Dark'], ['system', '🖥️', 'System']] as [$val, $emoji, $label])
                        <button type="button" x-on:click="$flux.appearance = '{{ $val }}'"
                            x-bind:class="$flux.appearance === '{{ $val }}' ? 'border-blue-500 ring-2 ring-blue-500/20' : 'border-[var(--color-hairline)]'"
                            class="flex items-center justify-between rounded-xl border p-4 text-sm font-semibold text-[var(--color-navy-900)] transition dark:text-zinc-100">
                            <span>{{ $emoji }} {{ __($label) }}</span>
                            <flux:icon name="check" variant="micro" class="size-4 text-blue-500" x-show="$flux.appearance === '{{ $val }}'" />
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ============================ PREFERENCES ============================ --}}
        @if ($section === 'preferences')
            <div class="mt-0.5 flex items-start justify-between gap-4">
                <p class="text-sm text-zinc-500">{{ __('Customize your CRM experience.') }}</p>
                <button type="button" wire:click="savePreferences" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Save') }}</button>
            </div>
            <div class="{{ $card }} mt-5 max-w-3xl divide-y divide-[var(--color-hairline)]">
                @foreach ([
                    ['compact_tables', 'Compact table rows', 'Show more rows on screen with less spacing'],
                    ['sidebar_counts', 'Show customer count in sidebar', 'Display live counts next to navigation items'],
                    ['auto_collapse', 'Auto-collapse sidebar on small screens', 'Sidebar hides automatically on screens under 1024px'],
                    ['dashboard_ai', 'Show AI insights on dashboard', 'Display the AI Insights panel on your dashboard'],
                ] as [$key, $title, $desc])
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div><div class="text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __($title) }}</div><div class="text-xs text-zinc-500">{{ __($desc) }}</div></div>
                        <label class="inline-flex cursor-pointer items-center"><input type="checkbox" wire:model="prefs.{{ $key }}" class="peer sr-only"><span class="{{ $toggle }}"></span></label>
                    </div>
                @endforeach
                <div class="flex items-center justify-between gap-4 py-3">
                    <div><div class="text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('Default customers tab') }}</div><div class="text-xs text-zinc-500">{{ __('Which tab to land on when opening the Customers page') }}</div></div>
                    <select wire:model="prefs.default_customers_tab" class="rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-1.5 text-sm dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
                        @foreach (['all' => 'All', 'active' => 'Active', 'monthly' => 'Monthly', 'yearly' => 'Yearly', 'unsuccessful' => 'Failed'] as $v => $t)
                            <option value="{{ $v }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        {{-- ============================ CLAIM SETTINGS ============================ --}}
        @if ($section === 'claims')
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Configure the service fee, covered systems, and appliance brands shown on the customer claim form.') }}</p>

            <div class="{{ $card }} mt-5">
                <h3 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="credit-card" variant="micro" class="size-4 text-blue-500" /> {{ __('Service Call Fee') }}</h3>
                <p class="text-xs text-zinc-500">{{ __('Amount charged to the customer each time a claim is submitted through the portal.') }}</p>
                <div class="mt-3 flex items-center gap-2">
                    <span class="text-sm text-zinc-400">$</span>
                    <input type="number" step="0.01" wire:model="claimFee" class="{{ $inp }} !mt-0 w-32">
                    <button type="button" wire:click="saveClaimFee" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Save') }}</button>
                </div>
                @error('claimFee') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="{{ $card }} mt-4">
                <div class="flex items-center justify-between">
                    <div><h3 class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Covered Systems') }}</h3><p class="text-xs text-zinc-500">{{ $systems->count() }} {{ __('systems · drives the claim form system dropdown') }}</p></div>
                </div>
                <div class="mt-3 flex gap-2">
                    <input type="text" wire:model="newSystem" placeholder="{{ __('New system name…') }}" class="{{ $inp }} !mt-0 flex-1" wire:keydown.enter="addSystem">
                    <button type="button" wire:click="addSystem" class="rounded-lg border border-[var(--color-hairline)] px-3 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10">+ {{ __('Add System') }}</button>
                </div>
                <div class="mt-3 divide-y divide-[var(--color-hairline)]">
                    @foreach ($systems as $sys)
                        <div wire:key="sys-{{ $sys->id }}" class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <span class="font-medium text-[var(--color-navy-900)] dark:text-zinc-100">{{ $sys->title }}</span>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-zinc-400">{{ $sys->issues_count }} {{ __('issues') }}</span>
                                <button type="button" wire:click="removeSystem({{ $sys->id }})" wire:confirm="{{ __('Remove :s?', ['s' => $sys->title]) }}" class="text-zinc-400 hover:text-red-600"><flux:icon name="trash" variant="micro" class="size-4" /></button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="{{ $card }} mt-4">
                <h3 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="tag" variant="micro" class="size-4 text-violet-500" /> {{ __('Appliance Brands') }}</h3>
                <p class="text-xs text-zinc-500">{{ $brands->count() }} {{ __('brands · shown in the brand dropdown on the claim form') }}</p>
                <div class="mt-3 flex gap-2">
                    <input type="text" wire:model="newBrand" placeholder="{{ __('Add brand name…') }}" class="{{ $inp }} !mt-0 flex-1" wire:keydown.enter="addBrand">
                    <button type="button" wire:click="addBrand" class="rounded-lg border border-[var(--color-hairline)] px-3 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10">+ {{ __('Add') }}</button>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($brands as $b)
                        <span wire:key="brand-{{ $b->id }}" class="inline-flex items-center gap-1 rounded-full bg-black/5 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                            {{ $b->title }}
                            <button type="button" wire:click="removeBrand({{ $b->id }})" class="text-zinc-400 hover:text-red-600"><flux:icon name="x-mark" variant="micro" class="size-3" /></button>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ============================ BUY NUMBERS ============================ --}}
        @if ($section === 'numbers')
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Manage the DID phone numbers used as outbound caller IDs.') }}</p>
            <div class="{{ $card }} mt-5 max-w-3xl">
                <div class="space-y-2">
                    @forelse ($numbers as $num)
                        <div wire:key="num-{{ $num->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--color-hairline)] p-3">
                            <div class="flex items-center gap-3">
                                <div class="grid size-9 place-items-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15"><flux:icon name="phone" variant="micro" class="size-4" /></div>
                                <div>
                                    <div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $num->state ?: __('DID Number') }}</div>
                                    <a href="tel:{{ $num->contact_no }}" class="text-sm text-blue-600 underline decoration-blue-600/30">{{ $num->contact_no }}</a>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" wire:click="toggleNumber({{ $num->id }})" role="switch" aria-checked="{{ $num->lock_number ? 'true' : 'false' }}"
                                    class="relative h-6 w-11 shrink-0 rounded-full transition {{ $num->lock_number ? 'bg-blue-600' : 'bg-zinc-300 dark:bg-white/15' }}">
                                    <span class="absolute top-0.5 size-5 rounded-full bg-white transition {{ $num->lock_number ? 'left-[1.375rem]' : 'left-0.5' }}"></span>
                                </button>
                                <button type="button" wire:click="removeNumber({{ $num->id }})" wire:confirm="{{ __('Remove this number?') }}" class="text-zinc-400 hover:text-red-600"><flux:icon name="trash" variant="micro" class="size-4" /></button>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-zinc-500">{{ __('No DID numbers yet.') }}</p>
                    @endforelse
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <input type="text" wire:model="newNumberLabel" placeholder="{{ __('Label (e.g. Payment Failed Line)') }}" class="{{ $inp }} !mt-0 flex-1 min-w-[10rem]">
                    <input type="text" wire:model="newNumber" placeholder="(888) 000-0000" class="{{ $inp }} !mt-0 flex-1 min-w-[9rem]">
                    <button type="button" wire:click="addNumber" class="rounded-lg border border-[var(--color-hairline)] px-3 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10">+ {{ __('Add DID Number') }}</button>
                </div>
            </div>
        @endif

        {{-- ============================ SECURITY & ACCESS ============================ --}}
        @if ($section === 'security')
            <div class="mt-0.5 flex items-start justify-between gap-4">
                <p class="text-sm text-zinc-500">{{ __('Authentication controls, network restrictions, always-on protections, and login activity.') }}</p>
                <button type="button" wire:click="saveSecurity" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Save') }}</button>
            </div>

            <div class="{{ $card }} mt-5">
                <h3 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="key" variant="micro" class="size-4 text-blue-500" /> {{ __('Authentication Protection') }}</h3>
                <div class="mt-3 divide-y divide-[var(--color-hairline)]">
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div><div class="text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('Session Timeout') }}</div><div class="text-xs text-zinc-500">{{ __('Automatically log out inactive users after this period') }}</div></div>
                        <select wire:model="security.session_timeout" class="rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-1.5 text-sm dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
                            @foreach ([15, 30, 60, 120, 240] as $m)<option value="{{ $m }}">{{ $m }} {{ __('minutes') }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div><div class="text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('Require Strong Passwords') }}</div><div class="text-xs text-zinc-500">{{ __('Minimum 12 characters with uppercase, number, and symbol') }}</div></div>
                        <label class="inline-flex cursor-pointer items-center"><input type="checkbox" wire:model="security.strong_passwords" class="peer sr-only"><span class="{{ $toggle }}"></span></label>
                    </div>
                    <div class="py-3">
                        <div class="flex items-center justify-between gap-4">
                            <div><div class="text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('Login Lockout') }}</div><div class="text-xs text-zinc-500">{{ __('Block an IP after repeated failed login attempts within a time window') }}</div></div>
                            <label class="inline-flex cursor-pointer items-center"><input type="checkbox" wire:model="security.login_lockout" class="peer sr-only"><span class="{{ $toggle }}"></span></label>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-4 text-xs">
                            <label>{{ __('Max failed attempts') }}
                                <select wire:model="security.lockout_max" class="ml-1 rounded border border-[var(--color-hairline)] bg-[var(--color-surface)] px-2 py-1 dark:bg-[var(--color-surface-2)] dark:text-zinc-100">@foreach ([3, 5, 10] as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach</select>
                            </label>
                            <label>{{ __('Lockout window') }}
                                <select wire:model="security.lockout_window" class="ml-1 rounded border border-[var(--color-hairline)] bg-[var(--color-surface)] px-2 py-1 dark:bg-[var(--color-surface-2)] dark:text-zinc-100">@foreach ([5, 15, 30, 60] as $n)<option value="{{ $n }}">{{ $n }} {{ __('min') }}</option>@endforeach</select>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="{{ $card }} mt-4 flex items-center justify-between gap-4">
                <div><h3 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="finger-print" variant="micro" class="size-4 text-violet-500" /> {{ __('Two-Factor Authentication') }}</h3><p class="text-xs text-zinc-500">{{ __('Users will need an authenticator app code on each login') }}</p></div>
                <label class="inline-flex cursor-pointer items-center"><input type="checkbox" wire:model="security.twofa" class="peer sr-only"><span class="{{ $toggle }}"></span></label>
            </div>

            <div class="{{ $card }} mt-4 flex items-center justify-between gap-4">
                <div><h3 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="globe-alt" variant="micro" class="size-4 text-blue-500" /> {{ __('IP Allowlist') }}</h3><p class="text-xs text-zinc-500">{{ __('Only allow login attempts from approved IP addresses or CIDR ranges') }}</p></div>
                <label class="inline-flex cursor-pointer items-center"><input type="checkbox" wire:model="security.ip_allowlist" class="peer sr-only"><span class="{{ $toggle }}"></span></label>
            </div>

            <div class="{{ $card }} mt-4">
                <h3 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="shield-check" variant="micro" class="size-4 text-emerald-500" /> {{ __('Always-On Protections') }}</h3>
                <p class="text-xs text-zinc-500">{{ __('Framework-enforced controls that cannot be disabled') }}</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ([
                        'CSRF protection on every POST / PUT / DELETE',
                        'Eloquent parameterized queries — no raw string interpolation',
                        'bcrypt password hashing (12 rounds)',
                        'Signed webhook verification (Authorize.Net sha512 HMAC)',
                        'Per-route permission middleware + superadmin bypass',
                        'Encrypted casts on Authorize.Net credentials',
                    ] as $p)
                        <div class="flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50/50 p-3 text-xs text-emerald-800 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <flux:icon name="check-circle" variant="micro" class="mt-0.5 size-4 shrink-0" /> {{ $p }}
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="{{ $card }} mt-4">
                <h3 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="clock" variant="micro" class="size-4" /> {{ __('Login Activity Log') }}</h3>
                <p class="py-6 text-center text-sm text-zinc-500">{{ __('Admin auth events are not yet audited — this log fills once the auth-event listener lands.') }}</p>
            </div>
        @endif

        {{-- ============================ INTEGRATIONS ============================ --}}
        @if ($section === 'integrations')
            <div class="mt-0.5 flex items-start justify-between gap-4">
                <p class="text-sm text-zinc-500">{{ __('Connect third-party communication and analytics tools.') }}</p>
                <button type="button" wire:click="saveIntegrations" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Save') }}</button>
            </div>

            <div class="{{ $card }} mt-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2"><div class="grid size-9 place-items-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15"><flux:icon name="table-cells" variant="micro" class="size-4" /></div><div><div class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Google Sheets') }}</div><div class="text-xs text-zinc-500">{{ __('Lead Journey Sync') }}</div></div></div>
                    <span class="rounded-full bg-black/5 px-2.5 py-1 text-xs font-semibold text-zinc-500 dark:bg-white/10">{{ $sheetsId ? __('Configured') : __('Not Connected') }}</span>
                </div>
                <input type="text" wire:model="sheetsId" placeholder="{{ __('Spreadsheet ID (e.g. 14uNG…)') }}" class="{{ $inp }}">
            </div>

            <div class="{{ $card }} mt-4 flex items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                <flux:icon name="credit-card" variant="micro" class="size-4" /> {{ __('Payment processing (Authorize.Net) is configured separately under') }}
                <a href="{{ route('settings.hub', ['section' => 'gateway']) }}" wire:navigate class="font-semibold underline">{{ __('Payment Gateway') }}</a>.
            </div>

            <h3 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Zapier Automations') }}</h3>
            <p class="text-sm text-zinc-500">{{ __('Paste your Zap webhook URLs below. When enabled, the CRM fires these on key events.') }}</p>
            <div class="mt-3 space-y-3">
                @foreach ($this->zapierEvents() as $key => $desc)
                    <div wire:key="zap-{{ $key }}" class="{{ $card }} !p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div><div class="text-sm font-semibold text-blue-600 dark:text-blue-400">{{ \Illuminate\Support\Str::headline($key) }}</div><div class="text-xs text-zinc-500">{{ $desc }}</div></div>
                            <label class="inline-flex cursor-pointer items-center"><input type="checkbox" wire:model="zapier.{{ $key }}.enabled" class="peer sr-only"><span class="{{ $toggle }}"></span></label>
                        </div>
                        <input type="url" wire:model="zapier.{{ $key }}.url" placeholder="https://hooks.zapier.com/hooks/catch/…" class="{{ $inp }} font-mono text-xs">
                    </div>
                @endforeach
            </div>

            <h3 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Inbound Webhook Endpoints') }}</h3>
            <p class="text-sm text-zinc-500">{{ __('Paste these URLs into your external tools. All are live and verified in the handler.') }}</p>
            <div class="mt-3 space-y-2" x-data="{ copied: null }">
                @foreach ($this->inboundWebhooks() as [$label, $url, $note])
                    <div class="{{ $card }} !p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $label }}</div>
                            <button type="button" x-on:click="navigator.clipboard.writeText('{{ $url }}'); copied = '{{ $url }}'; setTimeout(() => copied = null, 1200)"
                                class="rounded-md border border-[var(--color-hairline)] px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10">
                                <span x-text="copied === '{{ $url }}' ? '{{ __('Copied') }}' : '{{ __('Copy') }}'"></span>
                            </button>
                        </div>
                        <code class="mt-1 block truncate font-mono text-xs text-zinc-500">{{ $url }}</code>
                        <div class="mt-0.5 text-[11px] text-zinc-400">{{ $note }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ======================= LINK-OUT SECTIONS ======================= --}}
        @php($linkOut = [
            'gateway' => ['billing.accounts', 'Payment Gateway', 'Connect Authorize.Net merchant accounts, view billing-sync diagnostics and the webhook URL.'],
            'packages' => [null, 'Packages & Pricing', 'Per-tier pricing by property type and size is configured here — coming to the Settings hub next.'],
            'addons' => [null, 'Add-on Services', 'Enable / disable add-on services and set their monthly prices — coming to the Settings hub next.'],
        ])
        @if (array_key_exists($section, $linkOut))
            @php($lo = $linkOut[$section])
            <div class="{{ $card }} mt-5 max-w-xl">
                <p class="text-sm text-zinc-500">{{ __($lo[2]) }}</p>
                @if ($lo[0] && \Illuminate\Support\Facades\Route::has($lo[0]))
                    <a href="{{ route($lo[0]) }}" wire:navigate class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        {{ __('Open :name', ['name' => $lo[1]]) }} <flux:icon name="arrow-right" variant="micro" class="size-4" />
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
