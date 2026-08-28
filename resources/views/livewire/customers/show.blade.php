@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $hd = 'text-[11px] font-semibold uppercase tracking-wider text-zinc-400';
    $row = 'flex items-start justify-between gap-4 py-2 text-sm border-b border-[var(--color-hairline)] last:border-0';
    $dt = 'text-zinc-500';
    $dd = 'text-right font-semibold text-[var(--color-navy-900)] dark:text-zinc-100';
    $sv = (int) $customer->status;
    $statusKey = $this->statusKey();
    $initials = \Illuminate\Support\Str::of($customer->full_name ?: $customer->email)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $card0 = $customer->cardProfiles->sortByDesc('id')->first();
    $monthly = $invoiceStats['monthly_price'] ?: (float) ($customer->latestEligiblePolicyPaymentHistory->amount ?? 0);
    $supportPhone = '888-920-8280';
    $statusLabels = ['active' => 'Active', 'failed' => 'Failed', 'bypass' => 'Bypass', 'renewal' => 'Renewal', 'cancelled' => 'Cancelled', 'inactive' => 'Inactive'];
@endphp

<div class="w-full" x-data="{ tab: 'profile' }">
    {{-- Breadcrumb --}}
    <div class="mb-4 flex items-center gap-2 text-sm text-zinc-500">
        <a href="{{ route('customers.index') }}" wire:navigate class="inline-flex items-center gap-1 hover:text-blue-600">
            <flux:icon name="arrow-left" variant="micro" class="size-4" /> {{ __('Customers') }}
        </a>
        <span class="text-zinc-300">/</span>
        <span class="font-medium text-[var(--color-navy-900)] dark:text-zinc-200">{{ $customer->full_name ?: '—' }}</span>
    </div>

    {{-- ============================ HEADER ============================ --}}
    <div class="{{ $card }}">
        <div class="flex flex-wrap items-start gap-4">
            <div class="grid size-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 text-lg font-bold text-white">
                {{ strtoupper($initials) ?: '—' }}
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h1 class="truncate text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ $customer->full_name ?: '—' }}</h1>
                    <button type="button" wire:click="toggleStar" title="{{ __('Star') }}"
                        class="{{ $customer->star ? 'text-amber-400' : 'text-zinc-300 hover:text-amber-400' }}">
                        <flux:icon name="star" :variant="$customer->star ? 'solid' : 'outline'" class="size-5" />
                    </button>
                </div>
                @if ($customer->contact)
                    <a href="tel:{{ $customer->contact }}" class="text-sm font-medium text-blue-600 underline decoration-blue-600/30">{{ $customer->contact }}</a>
                @endif

                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="tel:{{ $customer->contact }}" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                        <flux:icon name="phone" variant="micro" class="size-4" /> {{ __('Call') }}
                    </a>
                    <a href="mailto:{{ $customer->email }}" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                        <flux:icon name="envelope" variant="micro" class="size-4" /> {{ __('Email') }}
                    </a>
                    <button type="button" wire:click="sendReceipt" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                        <flux:icon name="receipt-percent" variant="micro" class="size-4" /> {{ __('Send Receipt') }}
                    </button>
                    <button type="button" wire:click="sendClaimEmail" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                        <flux:icon name="arrow-top-right-on-square" variant="micro" class="size-4" /> {{ __('Send Claim Email') }}
                    </button>
                    <button type="button" wire:click="$set('showCancelModal', true)" class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-500/40 dark:hover:bg-red-500/10">
                        <flux:icon name="x-circle" variant="micro" class="size-4" /> {{ __('Cancel Policy') }}
                    </button>
                    <button type="button" wire:click="deleteCustomer" wire:confirm="{{ __('Archive this customer? They can be restored from the database.') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-500/40 dark:hover:bg-red-500/10">
                        <flux:icon name="trash" variant="micro" class="size-4" /> {{ __('Delete') }}
                    </button>
                    <button type="button" wire:click="openProfile" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-1.5 text-sm font-semibold text-white hover:bg-blue-700">
                        <flux:icon name="pencil-square" variant="micro" class="size-4" /> {{ __('Edit') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-[var(--color-hairline)] pt-4 text-xs">
            <span @class([
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold',
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $statusKey === 'active' || $statusKey === 'renewal',
                'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' => in_array($statusKey, ['failed', 'cancelled', 'inactive']),
                'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => $statusKey === 'bypass',
            ])>
                <span class="size-1.5 rounded-full bg-current"></span>
                {{ strtoupper($statusLabels[$statusKey]) }}
            </span>

            <span class="inline-flex items-center gap-2 rounded-full bg-black/5 px-2.5 py-1 font-medium text-zinc-600 dark:bg-white/5 dark:text-zinc-300">
                {{ __('AUTH STATUS') }}: {{ $customer->authorize_status ? strtoupper($customer->authorize_status) : __('VERIFICATION UNAVAILABLE') }}
                <button type="button" wire:click="retryAuth" class="font-semibold text-blue-600 underline">{{ __('Retry') }}</button>
            </span>

            <span @class([
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold',
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => ! $customer->login_off,
                'bg-zinc-200 text-zinc-600 dark:bg-white/10 dark:text-zinc-300' => $customer->login_off,
            ])>
                <flux:icon name="globe-alt" variant="micro" class="size-3.5" />
                {{ $customer->login_off ? __('PORTAL OFF') : __('PORTAL OPEN') }}
                <button type="button" wire:click="toggleLoginOff" class="underline">{{ $customer->login_off ? __('open') : __('close') }}</button>
            </span>

            <select wire:change="setStatus($event.target.value)"
                class="ml-auto rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-2.5 py-1 text-xs font-medium text-zinc-600 focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-200">
                @foreach ($statusLabels as $key => $label)
                    <option value="{{ $key }}" @selected($statusKey === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- ============================ INSIGHT ============================ --}}
    @if ($this->insight)
        <div x-data="{ open: true }" x-show="open" class="mt-4 flex items-start gap-3 rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm text-violet-900 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-200">
            <flux:icon name="sparkles" variant="solid" class="mt-0.5 size-5 shrink-0 text-violet-500" />
            <p class="flex-1">{{ $this->insight }}</p>
            <div class="flex shrink-0 gap-3 text-xs font-semibold">
                <button type="button" @click="open = false" class="inline-flex items-center gap-1 text-violet-600 hover:underline"><flux:icon name="check" variant="micro" class="size-3.5" /> {{ __('Mark Done') }}</button>
                <button type="button" @click="open = false" class="text-violet-400 hover:underline">{{ __('Dismiss') }}</button>
            </div>
        </div>
    @endif

    {{-- ============================ TABS ============================ --}}
    <div class="mt-5 flex flex-wrap gap-x-6 border-b border-[var(--color-hairline)] text-sm">
        @foreach ([
            'profile' => ['Profile', 'user'],
            'coverage' => ['Coverage', 'shield-check'],
            'invoices' => ['Invoices', 'document-text'],
            'claims' => ['Claims', 'wrench-screwdriver'],
            'activity' => ['Activity', 'bolt'],
            'notes' => ['Notes', 'chat-bubble-left-right'],
        ] as $key => $meta)
            <button type="button" @click="tab = '{{ $key }}'"
                class="-mb-px flex items-center gap-1.5 border-b-2 py-3 font-medium transition"
                :class="tab === '{{ $key }}' ? 'border-blue-600 text-blue-600' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'">
                <flux:icon name="{{ $meta[1] }}" variant="micro" class="size-4" /> {{ __($meta[0]) }}
                @if ($key === 'claims' && $claims->count()) <span class="text-zinc-400">({{ $claims->count() }})</span> @endif
                @if ($key === 'invoices' && $payments->count()) <span class="text-zinc-400">({{ $payments->count() }})</span> @endif
            </button>
        @endforeach
    </div>

    {{-- ============================ PROFILE TAB ============================ --}}
    <div x-show="tab === 'profile'" class="mt-5 space-y-5">
        <div class="grid gap-5 lg:grid-cols-2">
            <div class="{{ $card }}">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="{{ $hd }}">{{ __('Contact Info') }}</h2>
                    <button type="button" wire:click="openProfile" class="text-xs font-semibold text-blue-600 hover:underline">{{ __('Edit') }}</button>
                </div>
                <dl>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Email') }}</dt><dd class="{{ $dd }}"><a href="mailto:{{ $customer->email }}" class="text-blue-600">{{ $customer->email ?: '—' }}</a></dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Phone') }}</dt><dd class="{{ $dd }}">{{ $customer->contact ?: '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Address') }}</dt><dd class="{{ $dd }}">{{ $customer->address ?: '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('City / State / Zip') }}</dt><dd class="{{ $dd }}">{{ trim(collect([$customer->city, $customer->state, $customer->zip_code])->filter()->implode(', ')) ?: '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Client type') }}</dt><dd class="{{ $dd }}">{{ $customer->client_type ?: '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Joined') }}</dt><dd class="{{ $dd }}">{{ optional($customer->created_at)->format('M j, Y') ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="{{ $card }}">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="{{ $hd }}">{{ __('Policy Details') }}</h2>
                    <button type="button" wire:click="openPlan" class="text-xs font-semibold text-blue-600 hover:underline">{{ __('Edit') }}</button>
                </div>
                <dl>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Policy Number') }}</dt><dd class="{{ $dd }} text-blue-600">{{ $customer->policy_number ?: '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Package') }}</dt><dd class="{{ $dd }}">{{ $customer->propertyPackage->title ?? '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Plan') }}</dt><dd class="{{ $dd }}">{{ $customer->isMonthly() ? __('Monthly') : __('Yearly') }}@if(filled($customer->installment) && (int) $customer->installment > 1) · {{ $customer->installment }} {{ __('installments') }}@endif</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Salesman') }}</dt><dd class="{{ $dd }}">{{ $customer->salesman_name ?: '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Purchase Date') }}</dt><dd class="{{ $dd }}">{{ $customer->custom_purchaes_date ?: (optional($customer->created_at)->format('M j, Y') ?? '—') }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Coverage Start') }}</dt><dd class="{{ $dd }}">{{ $customer->custom_s_date ?: ($customer->start_date ?: '—') }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Coverage End') }}</dt><dd class="{{ $dd }}">{{ $customer->isMonthly() ? __('Ongoing — no end date') : ($customer->custom_e_date ?: ($customer->end_date ?: '—')) }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ __('Service Call Fee') }}</dt><dd class="{{ $dd }}">${{ number_format((float) $customer->call_fee, 2) }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">{{ $customer->isMonthly() ? __('Monthly Price') : __('Plan Price') }}</dt><dd class="{{ $dd }}">${{ number_format($monthly, 2) }}{{ $customer->isMonthly() ? '/mo' : '' }}</dd></div>
                </dl>
                @if ($customer->discount && $customer->discount !== '0')
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                        {{ __(':pct% discount applied', ['pct' => $customer->discount]) }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Payment on file --}}
        <div class="{{ $card }}">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="{{ $hd }}">{{ __('Payment on File') }}</h2>
                <button type="button" wire:click="{{ $showAddPayment ? 'cancelAddPayment' : 'addPaymentMethodForm' }}" class="text-xs font-semibold text-blue-600 hover:underline">
                    {{ $showAddPayment ? __('Close') : '+ '.__('Add Payment Method') }}
                </button>
            </div>

            {{-- Inline "add payment method" form — creates a card_profiles row
                 linked to this customer (client_id) + a CIM profile. --}}
            @if ($showAddPayment)
                <div class="mb-4 rounded-xl border border-[var(--color-hairline)] bg-black/[0.02] p-4 dark:bg-white/5"
                    x-data="addPaymentMethod(@js($this->anet))">
                    <div class="mb-3 inline-flex rounded-lg border border-[var(--color-hairline)] p-0.5 text-xs font-semibold">
                        <button type="button" wire:click="$set('pmMethod', 'card')" @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition', 'bg-blue-600 text-white' => $pmMethod === 'card', 'text-zinc-500' => $pmMethod !== 'card'])>
                            <flux:icon name="credit-card" variant="micro" class="size-3.5" /> {{ __('Card') }}
                        </button>
                        <button type="button" wire:click="$set('pmMethod', 'bank')" @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition', 'bg-blue-600 text-white' => $pmMethod === 'bank', 'text-zinc-500' => $pmMethod !== 'bank'])>
                            <flux:icon name="building-library" variant="micro" class="size-3.5" /> {{ __('Bank Account') }}
                        </button>
                    </div>

                    @if ($pmMethod === 'card')
                        <form x-on:submit.prevent="submit" class="space-y-3">
                            <div>
                                <label class="{{ $hd }}">{{ __('Card Number') }}</label>
                                <input type="text" inputmode="numeric" autocomplete="cc-number" x-model="card" x-on:input="card = fmtCard($event.target.value); $wire.set('pmCardNumber', digits(card), false)"
                                    placeholder="1234 5678 9012 3456" maxlength="22"
                                    class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 font-mono text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="{{ $hd }}">{{ __('Exp Month') }}</label>
                                    <input type="text" inputmode="numeric" maxlength="2" wire:model="pmExpMonth" placeholder="MM"
                                        class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
                                </div>
                                <div>
                                    <label class="{{ $hd }}">{{ __('Exp Year') }}</label>
                                    <input type="text" inputmode="numeric" maxlength="4" wire:model="pmExpYear" placeholder="YY"
                                        class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
                                </div>
                            </div>
                            <div>
                                <label class="{{ $hd }}">{{ __('Name on Card') }}</label>
                                <input type="text" autocomplete="cc-name" wire:model="pmName" placeholder="John Doe"
                                    class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
                            </div>
                            @error('pmCardNumber') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('pmExpMonth') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('pmExpYear') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <p x-show="tokenError" x-text="tokenError" class="text-sm text-red-600"></p>
                            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300">
                                {{ $this->liveMode() ? __('Card is tokenised by Authorize.Net Accept.js — the full number never reaches this server.') : __('Billing is in dry-run — a sandbox CIM profile is created, no real card is stored.') }}
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" x-bind:disabled="busy" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                                    <flux:icon name="plus" variant="micro" class="size-4" />
                                    <span x-show="!busy">{{ __('Save Payment Method') }}</span>
                                    <span x-show="busy">{{ __('Saving…') }}</span>
                                </button>
                                <button type="button" wire:click="cancelAddPayment" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('Cancel') }}</button>
                            </div>
                        </form>
                    @else
                        <form wire:submit="savePaymentMethod" class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="{{ $hd }}">{{ __('Routing Number') }}</label>
                                    <input type="text" inputmode="numeric" maxlength="9" wire:model="pmRouting"
                                        class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 font-mono text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
                                </div>
                                <div>
                                    <label class="{{ $hd }}">{{ __('Account Number') }}</label>
                                    <input type="text" inputmode="numeric" maxlength="17" wire:model="pmAccount"
                                        class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 font-mono text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
                                </div>
                                <div>
                                    <label class="{{ $hd }}">{{ __('Name on Account') }}</label>
                                    <input type="text" wire:model="pmName"
                                        class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
                                </div>
                                <div>
                                    <label class="{{ $hd }}">{{ __('Account Type') }}</label>
                                    <select wire:model="pmBankType" class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
                                        @foreach (config('sale_sheet.bank_account_types', ['Checking', 'Savings', 'Business Checking']) as $bt)
                                            <option value="{{ $bt }}">{{ $bt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @error('pmRouting') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('pmAccount') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('pmName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <div class="flex items-center gap-2">
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                    <flux:icon name="plus" variant="micro" class="size-4" /> {{ __('Save Payment Method') }}
                                </button>
                                <button type="button" wire:click="cancelAddPayment" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('Cancel') }}</button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif

            @forelse ($customer->cardProfiles->sortByDesc('id') as $cp)
                <div class="mb-2 flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3 {{ $loop->first ? 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-500/30 dark:bg-emerald-500/5' : 'border-[var(--color-hairline)]' }}">
                    <div class="flex items-center gap-3">
                        <span class="rounded-md bg-zinc-800 px-2 py-1 text-[10px] font-bold uppercase text-white">{{ $cp->routingNumber ? __('Bank') : ($cp->brand ?: __('Card')) }}</span>
                        <span class="font-mono text-sm text-[var(--color-navy-900)] dark:text-zinc-100">•••• •••• •••• {{ $cp->last4 ?: $cp->accountNumber ?: '••••' }}</span>
                        @if ($cp->expMonth)<span class="text-xs text-zinc-400">{{ __('Expires') }} {{ str_pad((string) $cp->expMonth, 2, '0', STR_PAD_LEFT) }}/{{ $cp->expYear }}</span>@endif
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($loop->first)<span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600"><flux:icon name="star" variant="solid" class="size-3" /> {{ __('ACTIVE') }}</span>@endif
                        <button type="button" wire:click="deleteCardProfile({{ $cp->id }})" wire:confirm="{{ __('Remove this payment method?') }}" class="text-zinc-400 hover:text-red-600"><flux:icon name="trash" variant="micro" class="size-4" /></button>
                    </div>
                </div>
                @if ($loop->first)
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-[var(--color-hairline)] px-3 py-2 text-xs">
                            <div class="{{ $hd }}">{{ __('Authorize.Net Customer Profile ID') }}</div>
                            <div class="mt-1 font-mono text-sm text-blue-600 underline">{{ $cp->customer_profile_id ?: '—' }}</div>
                        </div>
                        <div class="rounded-lg border border-[var(--color-hairline)] px-3 py-2 text-xs">
                            <div class="{{ $hd }}">{{ __('Authorize.Net Payment Profile ID') }}</div>
                            <div class="mt-1 font-mono text-sm text-blue-600 underline">{{ $cp->payment_profile_id ?: '—' }}</div>
                        </div>
                    </div>
                @endif
            @empty
                <p class="text-sm text-zinc-500">{{ __('No saved payment method on file.') }}</p>
            @endforelse

            <div class="mt-3 grid gap-3 rounded-xl border border-[var(--color-hairline)] p-3 text-xs sm:grid-cols-5">
                <div><div class="{{ $hd }}">{{ __('Recurring') }}</div><div class="mt-1 font-semibold">{{ $customer->isMonthly() ? __('Monthly') : __('Yearly') }}</div></div>
                <div><div class="{{ $hd }}">{{ __('Auth Account') }}</div><div class="mt-1 font-semibold">{{ $customer->authorizeAccount->name ?? ($customer->authorize_account_id ? 'Auth '.$customer->authorize_account_id : '—') }}</div></div>
                <div><div class="{{ $hd }}">{{ __('Subscription ID') }}</div><div class="mt-1 font-mono font-semibold text-blue-600 underline">{{ $latestSub->subscription_id ?? '—' }}</div></div>
                <div><div class="{{ $hd }}">{{ __('Auth Status') }}</div><div class="mt-1 font-semibold">{{ $customer->authorize_status ? ucfirst($customer->authorize_status) : '—' }} <span class="font-normal text-zinc-400">{{ optional($customer->updated_at)->diffForHumans(null, true) ? '· '.__('synced').' '.$customer->updated_at->diffForHumans() : '' }}</span></div></div>
                <div><div class="{{ $hd }}">{{ __('CIM Profile ID') }}</div><div class="mt-1 font-mono font-semibold text-blue-600 underline">{{ $card0->customer_profile_id ?? '—' }}</div></div>
            </div>
        </div>
    </div>

    {{-- ============================ COVERAGE TAB ============================ --}}
    <div x-show="tab === 'coverage'" x-cloak class="mt-5 space-y-5">
        <div class="flex justify-end">
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                <flux:icon name="arrow-down-tray" variant="micro" class="size-4" /> {{ __('Export PDF') }}
            </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Package', $customer->propertyPackage->title ?? '—', true],
                ['Plan', $customer->isMonthly() ? __('Monthly') : __('Yearly'), false],
                ['Property Type', $customer->propertyType->title ?? '—', false],
                ['Property Size', $customer->propertySize->title ?? '—', false],
            ] as [$k, $v, $accent])
                <div class="{{ $card }}">
                    <div class="{{ $hd }}">{{ __($k) }}</div>
                    <div class="mt-1 text-lg font-bold {{ $accent ? 'text-blue-600' : 'text-[var(--color-navy-900)] dark:text-zinc-100' }}">{{ $v }}</div>
                </div>
            @endforeach
        </div>

        <div class="{{ $card }}">
            <div class="flex items-start justify-between">
                <h2 class="{{ $hd }}">{{ __('Pricing') }}</h2>
                <div class="text-right">
                    <div class="text-3xl font-bold text-[var(--color-navy-900)] dark:text-white">${{ number_format($monthly, 2) }}</div>
                    <div class="text-xs text-zinc-400">{{ $customer->isMonthly() ? __('per month') : __('per term') }}</div>
                </div>
            </div>
            @if ($customer->discount && $customer->discount !== '0')
                <div class="mt-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    <flux:icon name="ticket" variant="micro" class="size-4" /> {{ __(':pct% discount applied', ['pct' => $customer->discount]) }}
                </div>
            @endif
        </div>

        <div class="{{ $card }}">
            <h2 class="{{ $hd }} mb-3">{{ __('Coverage Included') }}</h2>
            @if ($coverageItems->isNotEmpty())
                <div class="grid gap-x-8 gap-y-2 sm:grid-cols-2">
                    @foreach ($coverageItems as $item)
                        <div class="flex items-center gap-2 text-sm">
                            <flux:icon name="check-circle" variant="solid" class="size-4 shrink-0 text-emerald-500" />
                            <span class="text-[var(--color-navy-900)] dark:text-zinc-200">{{ $item }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-zinc-500">{{ __('No coverage line items are catalogued for this package.') }}</p>
            @endif

            @if ($selectedAddons->isNotEmpty())
                <div class="mt-4 border-t border-[var(--color-hairline)] pt-3">
                    <div class="{{ $hd }} mb-2">{{ __('Add-ons') }}</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($selectedAddons as $ad)
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">{{ $ad->title }} +${{ number_format((float) ($customer->isMonthly() ? $ad->monthly : $ad->yearly), 2) }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Policy receipt --}}
        @php
            $twoYear = round($monthly * 23, 2);
            $fiveYear = round($monthly * 54, 2);
            $half = (int) ceil($coverageItems->count() / 2);
        @endphp
        <div class="{{ $card }}">
            <h2 class="{{ $hd }} mb-3">{{ __('Policy Receipt') }}</h2>
            <div class="mx-auto max-w-2xl overflow-hidden rounded-xl border border-[var(--color-hairline)] bg-white text-zinc-800 shadow-sm">
                <div class="flex items-center justify-between bg-[var(--color-navy-900)] px-5 py-4 text-white">
                    <div class="flex items-center gap-2">
                        <flux:icon name="shield-check" variant="solid" class="size-6 text-blue-300" />
                        <div>
                            <div class="text-sm font-bold">American Home Coverage</div>
                            <div class="text-[11px] text-white/60">{{ __('Receipt & Policy Confirmation') }}</div>
                        </div>
                    </div>
                    <div class="text-right text-[11px]">
                        <div class="text-white/60">{{ __('Policy #') }}</div>
                        <div class="font-mono font-semibold">{{ $customer->policy_number ?: '—' }}</div>
                    </div>
                </div>

                <div class="space-y-4 p-5 text-sm">
                    <div>
                        <p class="font-semibold">{{ __('Hi :name,', ['name' => $customer->full_name ?: __('there')]) }}</p>
                        <p class="mt-1 text-zinc-500">{{ __('Thanks for choosing American Home Coverage. Below is your receipt and policy summary.') }}</p>
                        <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                            <flux:icon name="check" variant="micro" class="size-3" /> {{ __('Coverage Active') }}
                        </span>
                    </div>

                    <div class="flex items-start justify-between border-y border-zinc-100 py-3">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Policy') }}</div>
                            <div class="font-mono font-bold text-[var(--color-navy-900)]">#{{ $customer->policy_number ?: '—' }}</div>
                            <div class="mt-1 text-xs text-zinc-500">{{ __('Plan') }}: {{ $customer->propertyPackage->title ?? '—' }}</div>
                            <div class="text-xs text-zinc-500">{{ __('Billing') }}: {{ $customer->isMonthly() ? __('Monthly') : __('Yearly') }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Total (today)') }}</div>
                            <div class="text-xl font-bold text-[var(--color-navy-900)]">${{ number_format($monthly, 2) }}</div>
                            <div class="mt-1 text-xs text-zinc-500">{{ __('Payment') }}: ${{ number_format($monthly, 2) }}</div>
                            <div class="text-xs text-zinc-500">{{ __('Service Call Fee') }}: ${{ number_format((float) $customer->call_fee, 2) }}</div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Customer & Property') }}</div>
                            <div class="mt-1 font-medium">{{ $customer->full_name ?: '—' }}</div>
                            <div class="mt-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Covered Address') }}</div>
                            <div class="text-xs text-zinc-600">{{ $customer->address ?: '—' }}<br>{{ trim(collect([$customer->city, $customer->state, $customer->zip_code])->filter()->implode(', ')) }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Coverage Term') }}</div>
                            <div class="mt-1 text-xs text-zinc-600">{{ __('Start') }}: {{ $customer->custom_s_date ?: ($customer->start_date ?: '—') }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Quick Support') }}</div>
                            <div class="mt-1 text-xs text-zinc-600">{{ __('Call us anytime — 24/7.') }}</div>
                            <a href="tel:{{ $supportPhone }}" class="mt-1 inline-flex items-center gap-1 rounded-lg bg-[var(--color-navy-900)] px-2.5 py-1 text-xs font-semibold text-white"><flux:icon name="phone" variant="micro" class="size-3" /> {{ $supportPhone }}</a>
                        </div>
                    </div>

                    <div class="border-t border-zinc-100 pt-3">
                        <div class="font-semibold">{{ __("What's Covered") }} @if($customer->propertyPackage) ({{ $customer->propertyPackage->title }} {{ __('Plan') }}) @endif</div>
                        @if ($coverageItems->isNotEmpty())
                            <div class="mt-2 grid gap-x-6 gap-y-1 text-xs text-zinc-600 sm:grid-cols-2">
                                @foreach ($coverageItems as $item)<div>{{ $item }}</div>@endforeach
                            </div>
                        @else
                            <p class="mt-1 text-xs text-zinc-400">{{ __('Refer to the policy for the full list of covered items.') }}</p>
                        @endif
                    </div>

                    <div class="border-t border-zinc-100 pt-3">
                        <div class="font-semibold">{{ __('Client Portal') }}</div>
                        <a href="{{ \Illuminate\Support\Facades\Route::has('login.user') ? route('login.user') : '#' }}" class="mt-1 inline-block rounded-lg bg-[var(--color-navy-900)] px-3 py-1.5 text-xs font-semibold text-white">{{ __('Go to Client Portal') }}</a>
                        <div class="mt-2 text-xs text-zinc-500">{{ __('Username') }}: {{ $customer->email }}</div>
                        <div class="text-xs text-zinc-500">{{ __('Password') }}: {{ $customer->policy_number ?: '—' }}</div>
                    </div>

                    <div class="rounded-lg bg-zinc-50 p-3">
                        <div class="font-semibold">{{ __('Upgrade to Multi-Year & Save') }}</div>
                        <p class="text-xs text-zinc-500">{{ __('Lock in your rate. Call :phone and we will handle the upgrade.', ['phone' => $supportPhone]) }}</p>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-zinc-200 bg-white p-3">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-blue-600">{{ __('2-Year · 4 Months Free') }}</div>
                                <div class="text-lg font-bold text-[var(--color-navy-900)]">${{ number_format($twoYear, 2) }}</div>
                                <div class="text-[11px] text-zinc-500">{{ __('Effective') }}: ${{ number_format($twoYear / 2, 2) }}/yr · ${{ number_format($twoYear / 24, 2) }}/mo</div>
                            </div>
                            <div class="rounded-lg border border-zinc-200 bg-white p-3">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-blue-600">{{ __('5-Year · 1 Year Free') }}</div>
                                <div class="text-lg font-bold text-[var(--color-navy-900)]">${{ number_format($fiveYear, 2) }}</div>
                                <div class="text-[11px] text-zinc-500">{{ __('Effective') }}: ${{ number_format($fiveYear / 5, 2) }}/yr · ${{ number_format($fiveYear / 60, 2) }}/mo</div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-zinc-100 pt-3 text-center text-[11px] text-zinc-400">
                        American Home Coverage · {{ __('Support') }}: {{ $supportPhone }}<br>
                        {{ __('This is a summary only. Refer to the policy for complete terms and exclusions.') }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Portal activity --}}
        <div class="{{ $card }}">
            <h2 class="{{ $hd }} mb-3">{{ __('Portal Activity') }}</h2>
            @forelse ($loginActivity as $li)
                <div class="{{ $row }}">
                    <dt class="{{ $dt }}">{{ __('Signed in') }}</dt>
                    <dd class="{{ $dd }}">{{ optional($li->last_login_at ?? $li->created_at)->diffForHumans() ?? '—' }} <span class="font-normal text-zinc-400">{{ $li->ip_info }}</span></dd>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-zinc-500">{{ __('No portal activity yet.') }}</p>
            @endforelse
        </div>
    </div>

    {{-- ============================ INVOICES TAB ============================ --}}
    <div x-show="tab === 'invoices'" x-cloak class="mt-5 space-y-5">
        <div class="{{ $card }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Invoice History') }}</h2>
                    <p class="text-xs text-zinc-400">{{ __('Same-day charges before 9 PM CT can be voided · after settlement use Refund') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    @if ($latestSub)<span class="rounded-md bg-black/5 px-2 py-1 font-mono font-semibold text-blue-600 underline dark:bg-white/5">{{ $latestSub->subscription_id }}</span>@endif
                    @if ($card0)<span class="inline-flex items-center gap-1 rounded-md bg-black/5 px-2 py-1 font-medium text-zinc-600 dark:bg-white/5 dark:text-zinc-300"><flux:icon name="credit-card" variant="micro" class="size-3.5" /> {{ __('Card') }} ••••{{ $card0->last4 }} · {{ str_pad((string) $card0->expMonth, 2, '0', STR_PAD_LEFT) }}/{{ $card0->expYear }}</span>@endif
                    <button type="button" wire:click="openPlan" class="inline-flex items-center gap-1 rounded-md border border-[var(--color-hairline)] px-2.5 py-1 font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10"><flux:icon name="credit-card" variant="micro" class="size-3.5" /> {{ __('Billing Actions') }}</button>
                    <button type="button" wire:click="$set('showChargeModal', true)" class="inline-flex items-center gap-1 rounded-md border border-emerald-300 px-2.5 py-1 font-semibold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10"><flux:icon name="plus" variant="micro" class="size-3.5" /> {{ __('Add Invoice') }}</button>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @php
                    $stats = [
                        ['COLLECTED', '$'.number_format($invoiceStats['collected'], 2), $invoiceStats['paid_count'].' '.\Illuminate\Support\Str::plural('payment', $invoiceStats['paid_count']), 'emerald'],
                        ['OUTSTANDING', '$'.number_format($invoiceStats['outstanding'], 2), $invoiceStats['outstanding'] > 0 ? __('open balance') : __('No open balance'), 'zinc'],
                        ['VOIDED / REFUNDED', '$'.number_format($invoiceStats['reversed'], 2), $invoiceStats['reversed'] > 0 ? __('reversed') : __('None'), 'zinc'],
                        [$customer->isMonthly() ? 'MONTHLY PRICE' : 'PLAN PRICE', '$'.number_format($invoiceStats['monthly_price'], 2), $customer->isMonthly() ? __('per month') : __('per term'), 'blue'],
                        ['COMMISSION', '$'.number_format($invoiceStats['commission'], 2), __('Est. for :who · 90% of monthly', ['who' => $customer->salesman_name ?: __('salesman')]), 'amber'],
                        ['NET REVENUE', '$'.number_format($invoiceStats['net_revenue'], 2), __('10% margin'), 'violet'],
                    ];
                @endphp
                @foreach ($stats as [$k, $v, $sub, $c])
                    <div class="rounded-xl border border-[var(--color-hairline)] p-3">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ __($k) }}</div>
                        <div @class([
                            'mt-1 text-lg font-bold',
                            'text-emerald-600' => $c === 'emerald',
                            'text-blue-600' => $c === 'blue',
                            'text-amber-600' => $c === 'amber',
                            'text-violet-600' => $c === 'violet',
                            'text-[var(--color-navy-900)] dark:text-zinc-100' => $c === 'zinc',
                        ])>{{ $v }}</div>
                        <div class="mt-0.5 text-[11px] text-zinc-400">{{ $sub }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="{{ $card }} !p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="border-b border-[var(--color-hairline)] text-left">
                        <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                            <th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-hairline)]">
                        @forelse ($payments as $p)
                            <tr wire:key="p-{{ $p->id }}" class="[&>td]:px-4 [&>td]:py-3">
                                <td class="whitespace-nowrap text-zinc-500">{{ optional($p->created_at)->format('M j, Y') ?? $p->payment_date ?? '—' }}</td>
                                <td>
                                    <div class="font-medium text-[var(--color-navy-900)] dark:text-zinc-100">
                                        {{ $p->purpose ?: ($p->claim_payment ? __('Claim payment') : __('Policy billing — :pkg', ['pkg' => $customer->propertyPackage->title ?? __('plan')])) }}
                                        @if ($p->is_custom)<span class="ml-1 rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ __('manual') }}</span>@endif
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap gap-x-3 text-[11px] text-zinc-400">
                                        @if ($p->authorize_account_id)<span>{{ __('Acct') }} {{ $p->authorize_account_id }}</span>@endif
                                        @if ($p->transaction_id)<span>TXN:<span class="underline">{{ $p->transaction_id }}</span></span>@endif
                                        @if ($p->subscription_id)<span>SUB:<span class="underline">{{ $p->subscription_id }}</span></span>@endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $p->currency ?: '$' }}{{ number_format((float) $p->amount, 2) }}</td>
                                <td>
                                    <select wire:change="setPaymentStatus({{ $p->id }}, $event.target.value)"
                                        @class([
                                            'rounded-full border-0 px-2 py-0.5 text-xs font-semibold focus:outline-none',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $p->isPaid(),
                                            'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' => ! $p->isPaid(),
                                        ])>
                                        <option value="paid" @selected($p->isPaid())>{{ __('Paid') }}</option>
                                        <option value="unpaid" @selected(! $p->isPaid())>{{ __('Unpaid') }}</option>
                                    </select>
                                    @if ($p->refund_payment)<span class="ml-1 rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-semibold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ __('reversed') }}</span>@endif
                                    @if ($p->disputed)<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ __('disputed') }}</span>@endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    @if ($p->isPaid() && ! $p->disputed)
                                        <button type="button" wire:click="markDisputed({{ $p->id }})" wire:confirm="{{ __('Open a dispute on this payment?') }}" class="text-xs font-semibold text-amber-600 hover:underline">{{ __('Dispute') }}</button>
                                    @endif
                                    @if ($p->isPaid() && ! $p->refund_payment && filled($p->transaction_id))
                                        <button type="button" wire:click="reverse({{ $p->id }})" wire:confirm="{{ $p->can_void ? __('Void this payment?') : __('Refund this payment?') }}" class="ml-2 text-xs font-semibold text-red-600 hover:underline">{{ $p->can_void ? __('Void') : __('Refund') }}</button>
                                    @endif
                                    @if ($p->is_custom)
                                        <button type="button" wire:click="deletePayment({{ $p->id }})" wire:confirm="{{ __('Remove this manual charge row?') }}" class="ml-2 align-middle text-zinc-400 hover:text-red-600"><flux:icon name="trash" variant="micro" class="inline size-4" /></button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-12 text-center text-zinc-500">{{ __('No payments recorded.') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($payments->isNotEmpty())
                        <tfoot class="border-t border-[var(--color-hairline)] text-sm">
                            <tr class="[&>td]:px-4 [&>td]:py-3">
                                <td colspan="2" class="text-zinc-500">{{ __(':paid of :total paid', ['paid' => $invoiceStats['paid_count'], 'total' => $invoiceStats['total_count']]) }}</td>
                                <td colspan="3" class="text-right font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('Total collected') }}: ${{ number_format($invoiceStats['collected'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- ============================ CLAIMS TAB ============================ --}}
    <div x-show="tab === 'claims'" x-cloak class="mt-5">
        @if ($claims->isEmpty())
            <div class="{{ $card }} flex flex-col items-center justify-center py-16 text-center">
                <flux:icon name="shield-check" variant="outline" class="size-10 text-zinc-300" />
                <h3 class="mt-3 text-base font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('No Claims on File') }}</h3>
                <p class="mt-1 max-w-xs text-sm text-zinc-500">{{ __('Claims submitted through the AHC portal will appear here automatically.') }}</p>
            </div>
        @else
            <div class="{{ $card }} !p-0 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="border-b border-[var(--color-hairline)] text-left">
                            <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                                <th>#</th><th>{{ __('Date') }}</th><th>{{ __('Problem') }}</th><th>{{ __('Status') }}</th><th>{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-hairline)]">
                            @foreach ($claims as $cl)
                                <tr wire:key="cl-{{ $cl->id }}" class="[&>td]:px-4 [&>td]:py-3 {{ \Illuminate\Support\Facades\Route::has('claims.show') ? 'cursor-pointer hover:bg-black/[0.02] dark:hover:bg-white/5' : '' }}"
                                    @if (\Illuminate\Support\Facades\Route::has('claims.show')) onclick="Livewire.navigate('{{ route('claims.show', $cl) }}')" @endif>
                                    <td class="text-zinc-400">{{ $cl->id }}</td>
                                    <td class="whitespace-nowrap text-zinc-500">{{ optional($cl->created_at)->format('M j, Y') ?? $cl->datetime ?? '—' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($cl->problem, 60) ?: '—' }}</td>
                                    <td><span class="rounded-full bg-black/5 px-2 py-0.5 text-xs font-semibold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ \App\Enums\ClaimStatus::tryFrom((int) $cl->status)?->label() ?? $cl->status }}</span></td>
                                    <td class="whitespace-nowrap font-semibold">${{ number_format((float) $cl->total_price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- ============================ ACTIVITY TAB ============================ --}}
    <div x-show="tab === 'activity'" x-cloak class="mt-5">
        @php
            $events = $activity->map(fn ($a) => (object) [
                'kind' => 'track',
                'icon' => $a->button_text === 'Profile View' ? 'eye' : 'cursor-arrow-rays',
                'who' => optional($a->user)->first_name ?? __('System'),
                'text' => $a->comments ?: ($a->button_text ?: __('Action')),
                'at' => $a->created_at ?: $a->create_date,
            ])->concat($planLogs->map(fn ($lg) => (object) [
                'kind' => 'plan',
                'icon' => 'arrows-right-left',
                'who' => __('Plan change'),
                'text' => ($lg->old_payment_structure == 1 ? 'Monthly' : 'Yearly').' → '.($lg->new_payment_structure == 1 ? 'Monthly' : 'Yearly').' · '.ucfirst(str_replace('_', ' ', (string) $lg->status)).' (log #'.$lg->id.')',
                'at' => $lg->created_at,
            ]))->sortByDesc(fn ($e) => (string) $e->at)->values();
        @endphp
        <div class="{{ $card }}">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Activity Timeline') }}</h2>
                <span class="text-xs text-zinc-400">{{ $events->count() }} {{ \Illuminate\Support\Str::plural('event', $events->count()) }}</span>
            </div>
            @forelse ($events as $e)
                <div class="flex gap-3 border-b border-[var(--color-hairline)] py-3 last:border-0" wire:key="ev-{{ $loop->index }}">
                    <div class="grid size-8 shrink-0 place-items-center rounded-full bg-blue-600 text-xs font-bold text-white">{{ mb_substr($e->who, 0, 1) }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm"><span class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $e->who }}</span> <span class="text-zinc-500">· {{ $e->text }}</span></div>
                        <div class="mt-0.5 flex items-center gap-1 text-xs text-zinc-400">
                            <flux:icon name="clock" variant="micro" class="size-3" />
                            {{ $e->at ? \Illuminate\Support\Carbon::parse($e->at)->diffForHumans() : '—' }}
                        </div>
                    </div>
                    <flux:icon name="{{ $e->icon }}" variant="micro" class="size-4 shrink-0 text-zinc-300" />
                </div>
            @empty
                <p class="py-8 text-center text-sm text-zinc-500">{{ __('No activity recorded yet.') }}</p>
            @endforelse
        </div>
    </div>

    {{-- ============================ NOTES TAB ============================ --}}
    <div x-show="tab === 'notes'" x-cloak class="mt-5 space-y-5">
        @if ($customer->notes->isEmpty())
            <div class="flex items-center gap-3 rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm text-violet-900 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-200">
                <flux:icon name="sparkles" variant="solid" class="size-5 shrink-0 text-violet-500" />
                {{ __('No notes yet for this customer.') }}
            </div>
        @endif

        <div class="{{ $card }}">
            <form wire:submit="addNote">
                <textarea wire:model="noteBody" rows="3" placeholder="{{ __('Add a note about this customer…') }}"
                    x-on:keydown.meta.enter="$wire.addNote()" x-on:keydown.ctrl.enter="$wire.addNote()"
                    class="w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] p-3 text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100"></textarea>
                @error('noteBody') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-xs text-zinc-400">⌘↵ {{ __('to save') }}</span>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        <flux:icon name="paper-airplane" variant="micro" class="size-4" /> {{ __('Add Note') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="{{ $card }}">
            @forelse ($customer->notes as $note)
                <div class="flex gap-3 border-b border-[var(--color-hairline)] py-3 last:border-0" wire:key="n-{{ $note->id }}">
                    <div class="grid size-8 shrink-0 place-items-center rounded-full bg-zinc-200 text-xs font-bold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ mb_substr($note->author?->name ?? 'S', 0, 1) }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="whitespace-pre-line text-sm text-[var(--color-navy-900)] dark:text-zinc-100">{{ $note->notes }}</div>
                        <div class="mt-1 text-xs text-zinc-500">{{ $note->author?->name ?? __('System') }} · {{ $note->date_time ?: optional($note->created_at)->diffForHumans() }}</div>
                    </div>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-zinc-500">{{ __('No notes yet. Be the first to add one above.') }}</p>
            @endforelse
        </div>
    </div>

    {{-- ============================ MODALS ============================ --}}

    {{-- Edit Customer Profile flyout --}}
    <flux:modal wire:model="showProfileModal" variant="flyout" class="max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('Edit Customer Profile') }}</flux:heading>

            <div>
                <p class="{{ $hd }} mb-2">{{ __('Contact Info') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="profile.first_name" :label="__('First Name')" />
                    <flux:input wire:model="profile.last_name" :label="__('Last Name')" />
                    <flux:input wire:model="profile.email" type="email" :label="__('Email')" class="sm:col-span-2" />
                    <flux:input wire:model="profile.contact" :label="__('Phone')" class="sm:col-span-2" />
                </div>
            </div>

            <div>
                <p class="{{ $hd }} mb-2">{{ __('Address') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="profile.address" :label="__('Street')" class="sm:col-span-2" />
                    <flux:input wire:model="profile.city" :label="__('City')" />
                    <flux:input wire:model="profile.state" :label="__('State')" />
                    <flux:input wire:model="profile.zip_code" :label="__('Zip Code')" />
                </div>
            </div>

            <div>
                <p class="{{ $hd }} mb-2">{{ __('Policy') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:select wire:model="profile.status" :label="__('Status')">
                        <flux:select.option value="1">{{ __('Active') }}</flux:select.option>
                        <flux:select.option value="0">{{ __('Suspended') }}</flux:select.option>
                        <flux:select.option value="5">{{ __('Bypass billing') }}</flux:select.option>
                    </flux:select>
                    <flux:input wire:model="profile.authorize_status" :label="__('Auth Status')" />
                    <flux:input wire:model="profile.authorize_account_id" type="number" :label="__('AuthNet Account Index')" />
                </div>
            </div>

            <div>
                <p class="{{ $hd }} mb-2">{{ __('Payment') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="profile.card_last4" :label="__('Card Last Four')" maxlength="4" />
                    <flux:input wire:model="profile.card_expiry" :label="__('Card Expiry (MM/YY)')" placeholder="07/29" />
                </div>
                <p class="mt-1 text-xs text-zinc-400">{{ __('Display only — the card is vaulted at Authorize.Net.') }}</p>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showProfileModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveProfile">{{ __('Save Changes') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Edit Plan & Policy modal --}}
    <flux:modal wire:model="showPlanModal" class="max-w-3xl">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('Edit Plan & Policy') }}</flux:heading>

            <div>
                <p class="{{ $hd }} mb-2">{{ __('Plan Details') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:select wire:model.live="epPackage" :label="__('Package')">
                        @foreach ($this->packages as $pk)
                            <flux:select.option value="{{ $pk->id }}">{{ $pk->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="epStructure" :label="__('Plan Type')">
                        <flux:select.option value="1">{{ __('Monthly') }}</flux:select.option>
                        <flux:select.option value="2">{{ __('Yearly') }}</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="epPropertyType" :label="__('Property Type')">
                        <flux:select.option value="">{{ __('—') }}</flux:select.option>
                        @foreach ($this->propertyTypes as $pt)
                            <flux:select.option value="{{ $pt->id }}">{{ $pt->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="epPropertySize" :label="__('Property Size')">
                        <flux:select.option value="">{{ __('—') }}</flux:select.option>
                        @foreach ($this->propertySizes as $ps)
                            <flux:select.option value="{{ $ps->id }}">{{ $ps->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="epSalesman" :label="__('Salesman')" class="sm:col-span-2">
                        <flux:select.option value="">{{ __('—') }}</flux:select.option>
                        @foreach ($this->salesmen as $sm)
                            <flux:select.option value="{{ trim($sm->first_name.' '.$sm->last_name) }}">{{ trim($sm->first_name.' '.$sm->last_name) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div>
                <p class="{{ $hd }} mb-2">{{ __('Coverage Add-ons') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->addons as $ad)
                        <button type="button" wire:key="ep-ad-{{ $ad->id }}" wire:click="toggleEpAddon({{ $ad->id }})"
                            @class([
                                'rounded-full border px-3 py-1.5 text-xs font-medium transition',
                                'border-blue-600 bg-blue-600 text-white' => in_array($ad->id, $epAddons, true),
                                'border-[var(--color-hairline)] text-zinc-600 hover:border-blue-400 dark:text-zinc-300' => ! in_array($ad->id, $epAddons, true),
                            ])>
                            {{ $ad->title }} +${{ number_format((float) ($epStructure === '1' ? $ad->monthly : $ad->yearly), 2) }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--color-hairline)] bg-black/[0.02] p-4 dark:bg-white/5">
                <div>
                    <p class="{{ $hd }}">{{ __('Auto-calculated Price') }}</p>
                    <p class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">${{ number_format($this->autoPrice, 2) }}<span class="text-sm font-normal text-zinc-400">{{ $epStructure === '1' ? '/mo' : '' }}</span></p>
                </div>
                <button type="button" wire:click="resetPlanPrice" class="text-xs font-semibold text-blue-600 hover:underline">↺ {{ __('Reset to auto') }}</button>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <flux:input wire:model="epCustomPrice" type="number" step="0.01" :label="__('Custom Price Override')" :placeholder="__('optional')" />
                <flux:input wire:model="epCallFee" type="number" step="0.01" :label="__('Service Call Fee')" />
                <flux:input wire:model.live="epDiscount" type="number" step="1" :label="__('Discount %')" />
            </div>

            <div>
                <p class="{{ $hd }} mb-2">{{ __('Policy Dates') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="epPurchaseDate" type="date" :label="__('Purchase Date')" />
                    <flux:input wire:model="epStartDate" type="date" :label="__('Coverage Start')" />
                </div>
                @if ($epStructure === '1')
                    <p class="mt-2 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">{{ __('Monthly plans are ongoing with no end date.') }}</p>
                @endif
            </div>

            <div x-data="{ open: false }" class="rounded-xl border border-[var(--color-hairline)]">
                <button type="button" @click="open = !open" class="flex w-full items-center justify-between p-4 text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">
                    <span class="flex items-center gap-2"><flux:icon name="bolt" variant="micro" class="size-4 text-violet-500" /> {{ __('Billing Actions') }}</span>
                    <flux:icon name="chevron-down" variant="micro" class="size-4" x-bind:class="open && 'rotate-180'" />
                </button>
                <div x-show="open" x-cloak class="space-y-3 border-t border-[var(--color-hairline)] p-4 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="epChargeToday" class="rounded"> {{ __('Charge card today') }}</label>
                    @if ($epChargeToday)<flux:input wire:model="epChargeAmount" type="number" step="0.01" class="ml-6 w-40" :placeholder="__('amount')" />@endif

                    <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="epUpdateSub" class="rounded"> {{ __('Update AN subscription') }} @if($latestSub) <span class="text-blue-600">#{{ $latestSub->subscription_id }}</span> @endif</label>
                    @if ($epUpdateSub)<flux:input wire:model="epUpdateAmount" type="number" step="0.01" class="ml-6 w-40" :placeholder="__('new amount')" />@endif

                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="epCancelSub" class="rounded"> {{ __('Cancel AN subscription') }}</label>
                    <p class="ml-6 text-xs text-zinc-400">{{ __('Permanently stops recurring billing on Authorize.Net.') }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-[var(--color-hairline)] pt-4">
                <p class="text-xs text-zinc-400">{{ $this->liveMode() ? __('Changes are saved to the CRM and Authorize.Net.') : __('Dry-run — changes save to the CRM only.') }}</p>
                <div class="flex gap-2">
                    <flux:button variant="ghost" wire:click="$set('showPlanModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" wire:click="savePlan">{{ __('Save Changes') }}</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- Custom charge modal --}}
    <flux:modal wire:model="showChargeModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Charge custom amount') }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ $this->liveMode() ? __("This will charge the customer's saved card.") : __('Billing is in dry-run mode — this records a payment row without charging.') }}
            </flux:text>
            <flux:input wire:model="chargeAmount" type="number" step="0.01" :label="__('Amount')" />
            <flux:input wire:model="chargeReason" :label="__('Reason / description')" />
            @error('chargeAmount') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            @error('chargeReason') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showChargeModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="chargeCustom">{{ __('Charge') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Cancel policy modal --}}
    <flux:modal wire:model="showCancelModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Cancel policy') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Cancels the recurring subscription and takes the customer offline (status + portal login).') }}</flux:text>
            <flux:textarea wire:model="cancelReason" rows="2" :label="__('Reason (optional)')" />
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">{{ __('Keep policy') }}</flux:button>
                <flux:button variant="danger" wire:click="cancelPolicy">{{ __('Cancel policy') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    @script
    <script>
        Alpine.data('addPaymentMethod', (cfg) => ({
            card: '', busy: false, tokenError: '',

            digits(v) { return (v || '').replace(/\D/g, ''); },
            fmtCard(v) { return this.digits(v).slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 '); },

            loadAcceptJs() {
                return new Promise((resolve, reject) => {
                    if (window.Accept) return resolve();
                    const s = document.createElement('script');
                    s.src = cfg.accept_js_url;
                    s.onload = () => resolve();
                    s.onerror = () => reject(new Error('Could not load Accept.js from Authorize.Net.'));
                    document.head.appendChild(s);
                });
            },

            tokenise(pan) {
                return new Promise((resolve, reject) => {
                    window.Accept.dispatchData({
                        authData: { clientKey: cfg.client_key, apiLoginID: cfg.login_id },
                        cardData: { cardNumber: pan, month: this.$wire.pmExpMonth, year: this.$wire.pmExpYear },
                    }, (res) => {
                        res.messages.resultCode === 'Ok'
                            ? resolve({ d: res.opaqueData.dataDescriptor, v: res.opaqueData.dataValue })
                            : reject(new Error(res.messages.message[0].text));
                    });
                });
            },

            async submit() {
                this.tokenError = '';
                const pan = this.digits(this.card);
                if (pan.length < 12) { this.tokenError = 'Please enter a valid card number.'; return; }

                this.busy = true;
                try {
                    if (cfg.live && cfg.login_id && cfg.client_key) {
                        await this.loadAcceptJs();
                        const tok = await this.tokenise(pan);
                        this.$wire.set('pmDescriptor', tok.d, false);
                        this.$wire.set('pmValue', tok.v, false);
                        this.$wire.set('pmCardNumber', '', false);
                    } else {
                        // Dry-run: DryRunPaymentGateway builds a synthetic CIM
                        // profile; it only needs the raw digits + expiry.
                        this.$wire.set('pmDescriptor', '', false);
                        this.$wire.set('pmValue', '', false);
                        this.$wire.set('pmCardNumber', pan, false);
                    }
                    this.$wire.set('pmLast4', pan.slice(-4), false);
                    await this.$wire.savePaymentMethod();
                } catch (e) {
                    this.tokenError = e.message || 'The card could not be tokenised.';
                } finally {
                    this.busy = false;
                }
            },
        }));
    </script>
    @endscript
</div>
