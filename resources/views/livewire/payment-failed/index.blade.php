@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $lbl = 'text-[10px] font-semibold uppercase tracking-wide text-zinc-400';
    $h2 = 'font-bold text-[var(--color-navy-900)] dark:text-white';
    $money = fn ($n) => '$'.number_format((float) $n, 2);
    $tabs = [
        'overview' => ['Overview', 'squares-2x2'],
        'queue' => ['Queue', 'exclamation-triangle'],
        'outreach' => ['Daily Outreach', 'bell'],
        'schedule' => ['Charge Schedule', 'calendar-days'],
        'timing' => ['Smart Timing', 'clock'],
        'templates' => ['Templates', 'document-text'],
        'destinations' => ['Destinations', 'link'],
        'activity' => ['Activity', 'chart-bar-square'],
        'analytics' => ['Analytics', 'chart-bar'],
        'safety' => ['Safety', 'shield-check'],
    ];
    $modeLabel = ['test' => 'Test Mode', 'live' => 'Live Mode', 'paused' => 'Paused'][$cfg['operating_mode'] ?? 'paused'];
@endphp

<div class="w-full">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">{{ __('Payment Failed') }}</p>
            <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Recovery control center') }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('One safe control center for recovery, outreach, charging, and audit history.') }}</p>
        </div>
        <span @class([
            'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold',
            'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => ($cfg['operating_mode'] ?? '') === 'paused',
            'bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300' => ($cfg['operating_mode'] ?? '') === 'test',
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => ($cfg['operating_mode'] ?? '') === 'live',
        ])>
            <span class="size-1.5 rounded-full bg-current"></span> {{ __($modeLabel) }}
        </span>
    </div>

    {{-- Tab bar --}}
    <div class="mt-4 flex flex-wrap gap-1 border-b border-[var(--color-hairline)] pb-px text-sm font-semibold">
        @foreach ($tabs as $key => [$label, $icon])
            <button type="button" wire:click="setTab('{{ $key }}')" @class([
                'inline-flex items-center gap-1.5 rounded-t-lg px-3 py-2 transition',
                'border-b-2 border-blue-600 text-blue-600' => $tab === $key,
                'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== $key,
            ])>
                <flux:icon name="{{ $icon }}" variant="micro" class="size-4" /> {{ __($label) }}
                @if ($key === 'queue' && isset($kpi))
                    <span class="rounded-full bg-rose-100 px-1.5 text-[11px] text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">{{ $kpi['failed'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- =============================================== OVERVIEW =============================================== --}}
    @if ($tab === 'overview')
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="{{ $card }}"><div class="{{ $lbl }} text-rose-500">{{ __('Active failures') }}</div><div class="mt-1 text-3xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($overview['active']) }}</div></div>
            <div class="{{ $card }}"><div class="{{ $lbl }} text-emerald-500">{{ __('Resolved today') }}</div><div class="mt-1 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($overview['resolved_session']) }}</div></div>
            <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Daily outreach') }}</div><div class="mt-1 text-3xl font-bold {{ $overview['outreach_on'] ? 'text-emerald-600' : 'text-zinc-400' }}">{{ $overview['outreach_on'] ? __('On') : __('Off') }}</div></div>
            <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Charging mode') }}</div><div class="mt-1 text-3xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __($modeLabel) }}</div></div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="{{ $card }}">
                <div class="flex items-center justify-between">
                    <h2 class="{{ $h2 }}">{{ __('Recovery plan') }}</h2>
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-semibold text-zinc-500 dark:bg-white/10">
                        {{ ($cfg['operating_mode'] ?? '') === 'live' ? __('Enabled') : __('Disabled') }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-zinc-500">{{ __('The manual failed-payment audience stays unchanged. This plan controls what happens next.') }}</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Charge schedule') }}</dt><dd class="text-right font-semibold">{{ $overview['schedule'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Outreach') }}</dt><dd class="font-semibold">{{ $overview['outreach_on'] ? __('On') : __('Off') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Channels') }}</dt><dd class="font-semibold">{{ $overview['channels'] ? implode(', ', array_map('ucfirst', $overview['channels'])) : __('None') }}</dd></div>
                </dl>
                <button type="button" wire:click="setTab('safety')" class="mt-3 text-sm font-semibold text-blue-600">{{ __('Review safety controls') }} →</button>
            </div>

            <div class="{{ $card }}">
                <h2 class="{{ $h2 }}">{{ __('Current safeguards') }}</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @php
                        $flag = fn ($on, $goodOn = false) => $on
                            ? ($goodOn ? ['On', 'text-emerald-600'] : ['Blocked', 'text-emerald-600'])
                            : ($goodOn ? ['Off', 'text-zinc-400'] : ['Allowed', 'text-amber-600']);
                    @endphp
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Real charges') }}</dt><dd class="font-semibold {{ $overview['safeguards']['real_charges'] ? 'text-amber-600' : 'text-emerald-600' }}">{{ $overview['safeguards']['real_charges'] ? __('Allowed') : __('Blocked') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Emergency pause') }}</dt><dd class="font-semibold {{ $overview['safeguards']['emergency_pause'] ? 'text-emerald-600' : 'text-zinc-400' }}">{{ $overview['safeguards']['emergency_pause'] ? __('On') : __('Off') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Outbound outreach') }}</dt><dd class="font-semibold {{ $overview['safeguards']['outbound_outreach'] ? 'text-amber-600' : 'text-zinc-400' }}">{{ $overview['safeguards']['outbound_outreach'] ? __('On') : __('Off') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Audience mutations') }}</dt><dd class="font-semibold {{ $overview['safeguards']['audience_mutations'] ? 'text-amber-600' : 'text-emerald-600' }}">{{ $overview['safeguards']['audience_mutations'] ? __('Allowed') : __('Blocked in Test Mode') }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="{{ $card }} mt-5">
            <h2 class="{{ $h2 }}">{{ __('How payment recovery works') }}</h2>
            <div class="mt-3 grid gap-4 md:grid-cols-3 text-sm text-zinc-500">
                <div><div class="font-semibold text-[var(--color-navy-900)] dark:text-white">{{ __('Monthly plans') }}</div><p class="mt-1">{{ __('A recovery balance never exceeds four monthly payments. After a verified charge we check the existing Authorize.net subscription and restart it on the next signup-day anniversary if needed.') }}</p></div>
                <div><div class="font-semibold text-[var(--color-navy-900)] dark:text-white">{{ __('Installment plans') }}</div><p class="mt-1">{{ __('We count recorded paid installments before creating a replacement, so the new schedule has exactly the remaining occurrences and ends on time.') }}</p></div>
                <div><div class="font-semibold text-[var(--color-navy-900)] dark:text-white">{{ __('Duplicate protection') }}</div><p class="mt-1">{{ __('We never create a second subscription until the existing one has been checked. The result records the charge id, old and new subscription ids, and the scheduled end date.') }}</p></div>
            </div>
        </div>
    @endif

    {{-- =============================================== QUEUE =============================================== --}}
    @if ($tab === 'queue')
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            <div class="{{ $card }} border-rose-200 dark:border-rose-500/30"><div class="{{ $lbl }} text-rose-500">{{ __('Failed payments') }}</div><div class="mt-1 text-3xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($kpi['failed']) }}</div><div class="text-[11px] text-zinc-400">{{ __('need attention') }}</div></div>
            <div class="{{ $card }} border-amber-200 dark:border-amber-500/30"><div class="{{ $lbl }} text-amber-500">{{ __('Revenue at risk') }}</div><div class="mt-1 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ $money($kpi['risk']) }}</div><div class="text-[11px] text-zinc-400">{{ __('across the active queue') }}</div></div>
            <div class="{{ $card }} border-emerald-200 dark:border-emerald-500/30"><div class="{{ $lbl }} text-emerald-500">{{ __('Resolved today') }}</div><div class="mt-1 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($kpi['resolved_session']) }}</div><div class="text-[11px] text-zinc-400">{{ __('marked recovered') }}</div></div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex rounded-xl border border-[var(--color-hairline)] p-1 text-sm font-semibold">
                <button type="button" wire:click="setQtab('active')" @class(['rounded-lg px-4 py-1.5', 'bg-blue-600 text-white' => $qtab === 'active', 'text-zinc-500' => $qtab !== 'active'])>{{ __('Active') }} <span class="ml-1 text-xs">{{ $kpi['failed'] }}</span></button>
                <button type="button" wire:click="setQtab('resolved')" @class(['rounded-lg px-4 py-1.5', 'bg-blue-600 text-white' => $qtab === 'resolved', 'text-zinc-500' => $qtab !== 'resolved'])>{{ __('Resolved History') }}</button>
            </div>
            <div class="flex items-center gap-2">
                <flux:input wire:model.live.debounce.400ms="qsearch" icon="magnifying-glass" :placeholder="__('Customer, policy, email…')" class="max-w-xs" />
                <a href="{{ route('payment-failed.board') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-navy-900)] px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
                    <flux:icon name="play" variant="micro" class="size-3.5" /> {{ __('Start Board') }}
                </a>
            </div>
        </div>

        <div class="{{ $card }} mt-4 overflow-x-auto">
            @if ($qtab === 'active')
                <table class="w-full min-w-[70rem] text-sm">
                    <thead>
                        <tr class="border-b border-[var(--color-hairline)] text-left {{ $lbl }}">
                            <th class="py-2 pr-3">{{ __('Customer') }}</th>
                            <th class="px-3">{{ __('Policy #') }}</th>
                            <th class="px-3">{{ __('Amount') }}</th>
                            <th class="px-3">{{ __('Plan Type') }}</th>
                            <th class="px-3">{{ __('Failure Date') }}</th>
                            <th class="px-3">{{ __('Reason') }}</th>
                            <th class="px-3">{{ __('Retry Schedule') }}</th>
                            <th class="px-3">{{ __('Transaction ID') }}</th>
                            <th class="px-3">{{ __('Account') }}</th>
                            <th class="px-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-hairline)]">
                        @forelse ($rows as $r)
                            <tr wire:key="q-{{ $r->id }}">
                                <td class="py-3 pr-3">
                                    <a href="{{ route('customers.show', $r->id) }}" wire:navigate class="font-semibold text-[var(--color-navy-900)] hover:text-blue-600 dark:text-white">{{ $r->name }}</a>
                                </td>
                                <td class="px-3 text-zinc-500">{{ $r->policy_number }}</td>
                                <td class="px-3 font-semibold">{{ $money($r->amount) }}</td>
                                <td class="px-3">{{ $r->plan_type }}</td>
                                <td class="px-3 text-zinc-500">{{ $r->failed_at ? \Illuminate\Support\Carbon::parse($r->failed_at)->format('M j, Y g:i A') : '—' }}</td>
                                <td class="px-3"><span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">{{ $r->reason }}</span></td>
                                <td class="px-3">
                                    <div class="font-semibold">{{ __('Attempt :n of :max', ['n' => $r->attempts, 'max' => 15]) }}</div>
                                    @if ($r->next_attempt)
                                        <div class="text-[11px] text-amber-600">{{ $r->next_attempt->format('M j, g A') }}</div>
                                    @endif
                                </td>
                                <td class="px-3 text-zinc-500">{{ $r->transaction_id }}</td>
                                <td class="px-3 text-zinc-500">{{ $r->account }}</td>
                                <td class="px-3">
                                    <div class="flex flex-col items-end gap-1">
                                        <flux:button size="xs" variant="ghost" icon="phone" wire:click="claimAndCall({{ $r->id }})">{{ __('Claim & Call') }}</flux:button>
                                        <flux:button size="xs" variant="ghost" wire:click="markContacted({{ $r->id }})">{{ __('Contacted') }}</flux:button>
                                        <flux:button size="xs" variant="primary" icon="credit-card" wire:click="retry({{ $r->id }})">{{ __('Retry') }}</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="py-10 text-center text-zinc-400">{{ __('No failed payments in the queue.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $rows->links() }}</div>
            @else
                <ul class="divide-y divide-[var(--color-hairline)] text-sm">
                    @forelse ($resolved as $row)
                        <li class="flex items-center justify-between gap-4 py-2.5" wire:key="rh-{{ $row->id }}">
                            <div>
                                <span class="font-semibold text-[var(--color-navy-900)] dark:text-white">{{ $row->client?->full_name ?? '#'.$row->client_id }}</span>
                                <span class="text-zinc-500">— {{ $row->comments }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ $row->button_text }}</span>
                                <span class="text-xs text-zinc-400">{{ $row->create_date }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="py-10 text-center text-zinc-400">{{ __('Nothing resolved yet.') }}</li>
                    @endforelse
                </ul>
            @endif
        </div>
    @endif

    {{-- =============================== SETTINGS TABS (shared save bar) =============================== --}}
    @php $isSettings = in_array($tab, ['outreach', 'schedule', 'timing', 'templates', 'destinations', 'safety'], true); @endphp

    @if ($tab === 'outreach')
        <div class="{{ $card }} mt-5 space-y-4">
            <div class="flex items-center justify-between">
                <div><h2 class="{{ $h2 }}">{{ __('Daily Outreach') }}</h2><p class="text-xs text-zinc-500">{{ __('Every active failed-payment customer receives the enabled channels once per day at this local time.') }}</p></div>
                <flux:switch wire:model="cfg.outreach.enabled" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input type="time" wire:model="cfg.outreach.send_time" :label="__('Daily send time')" />
                <flux:input wire:model="cfg.outreach.timezone" :label="__('Timezone')" />
                <flux:input value="{{ __('All active failed payments') }}" :label="__('Audience')" readonly />
            </div>
        </div>
        <div class="{{ $card }} mt-4">
            <h3 class="{{ $h2 }}">{{ __('Required channels') }}</h3>
            <p class="text-xs text-zinc-500">{{ __('Each enabled channel is sent through its own destination. Test Mode creates previews only.') }}</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach (['sms' => ['SMS', 'Short message with update link'], 'email' => ['Email', 'Payment details and secure link'], 'voicemail' => ['Voicemail', 'Callback request and policy reference']] as $ch => [$name, $desc])
                    <label class="flex items-start justify-between gap-3 rounded-xl border border-[var(--color-hairline)] p-3">
                        <span><span class="block text-sm font-semibold">{{ __($name) }}</span><span class="block text-[11px] text-zinc-400">{{ __($desc) }}</span></span>
                        <flux:switch wire:model="cfg.outreach.channels.{{ $ch }}" />
                    </label>
                @endforeach
            </div>
            <flux:callout class="mt-3" variant="secondary" icon="lock-closed">{{ __('Outbound comms are globally disabled in this environment — toggles are saved as intent only, nothing is sent.') }}</flux:callout>
        </div>
        <div class="{{ $card }} mt-4">
            <h3 class="{{ $h2 }}">{{ __('Stop and retry rules') }}</h3>
            <div class="mt-3 space-y-3 text-sm">
                <div class="flex items-center justify-between"><span>{{ __('Stop after a customer replies') }}</span><flux:switch wire:model="cfg.outreach.stop_after_reply" /></div>
                <div class="flex items-center justify-between"><span>{{ __('Stop after a successful payment') }}</span><flux:switch wire:model="cfg.outreach.stop_after_payment" /></div>
                <div class="flex items-center justify-between"><span>{{ __('Honor opt-outs immediately') }}</span><flux:switch wire:model="cfg.outreach.honor_optouts" /></div>
            </div>
        </div>
    @endif

    @if ($tab === 'schedule')
        <div class="{{ $card }} mt-5">
            <div class="flex items-center justify-between">
                <div><h2 class="{{ $h2 }}">{{ __('Charge Schedule') }}</h2><p class="text-xs text-zinc-500">{{ __('Independent charge days and local times. No charge runs while Test Mode or the emergency pause is active.') }}</p></div>
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('Preview only') }}</span>
            </div>
            <div class="mt-4 space-y-2">
                @foreach (($cfg['charge_schedule'] ?? []) as $idx => $slot)
                    <div class="grid grid-cols-2 gap-3 sm:max-w-md" wire:key="cs-{{ $idx }}">
                        <flux:input type="number" min="1" wire:model="cfg.charge_schedule.{{ $idx }}.day" :label="__('Attempt :n · day', ['n' => $idx + 1])" />
                        <flux:input type="time" wire:model="cfg.charge_schedule.{{ $idx }}.time" :label="__('Time')" />
                    </div>
                @endforeach
            </div>
            <flux:callout class="mt-4" variant="secondary" icon="information-circle">
                {{ __('Max monthly retry windows controls how many months the later 1st/15th/billing-day pattern continues after the first attempts. Monthly overdue cap separately limits a monthly customer’s recovery balance to four premiums.') }}
            </flux:callout>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <flux:input type="number" min="0" wire:model="cfg.max_monthly_retry_windows" :label="__('Max monthly retry windows')" />
                <flux:input wire:model="cfg.charge_timezone" :label="__('Charge timezone')" />
                <flux:input type="number" min="1" wire:model="cfg.monthly_overdue_cap" :label="__('Monthly overdue cap')" />
            </div>
        </div>
    @endif

    @if ($tab === 'timing')
        <div class="{{ $card }} mt-5">
            <h2 class="{{ $h2 }}">{{ __('Smart Timing') }}</h2>
            <p class="text-xs text-zinc-500">{{ __('Use only customer-provided billing signals and explain every timing adjustment. These controls never access bank accounts, deposits, or protected financial data.') }}</p>
            <div class="mt-3 space-y-3 text-sm">
                @foreach ([
                    'original_billing_day' => ['Original billing day', 'Prefer the customer’s original billing day when it is available.'],
                    'pay_cycle' => ['Customer-provided pay-cycle information', 'Use pay-cycle information entered by the customer or an administrator.'],
                    'previous_payment_timing' => ['Previous successful-payment timing', 'Learn from the customer’s own prior successful payment times.'],
                    'signup_billing_time' => ['Signup and original billing time', 'Keep the original time-of-day pattern when it is meaningful.'],
                ] as $key => [$name, $desc])
                    <div class="flex items-start justify-between gap-4">
                        <span><span class="block font-semibold">{{ __($name) }}</span><span class="block text-[11px] text-zinc-400">{{ __($desc) }}</span></span>
                        <flux:switch wire:model="cfg.smart_timing.{{ $key }}" />
                    </div>
                @endforeach
            </div>
        </div>
        <div class="{{ $card }} mt-4" x-data="{ p: '' }">
            <h3 class="{{ $h2 }}">{{ __('Administrator patterns') }}</h3>
            <p class="text-xs text-zinc-500">{{ __('Approved local patterns such as “Thursday 12:00”. Transparent and editable.') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (($cfg['smart_timing']['admin_patterns'] ?? []) as $i => $pattern)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-600/10 px-3 py-1 text-xs font-semibold text-blue-700 dark:text-blue-300" wire:key="pat-{{ $i }}">
                        <flux:icon name="clock" variant="micro" class="size-3" /> {{ $pattern }}
                        <button type="button" wire:click="removePattern({{ $i }})" class="text-blue-400 hover:text-rose-500">&times;</button>
                    </span>
                @endforeach
            </div>
            <div class="mt-3 flex items-end gap-2">
                <flux:input x-model="p" :label="__('Add pattern')" placeholder="Tuesday 10:00" class="max-w-xs" />
                <flux:button size="sm" x-on:click="$wire.addPattern(p); p = ''">{{ __('Add') }}</flux:button>
            </div>
        </div>
    @endif

    @if ($tab === 'templates')
        <div class="{{ $card }} mt-5 space-y-3">
            <h2 class="{{ $h2 }}">{{ __('Outreach Templates') }}</h2>
            <p class="text-xs text-zinc-500">{{ __('Customer-facing content for the three daily channels. Variables: {firstName} {policyNumber} {amount} {updateLink}') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="cfg.templates.email_subject" :label="__('Email subject')" />
                <flux:input wire:model="cfg.templates.email_from_name" :label="__('From name')" />
                <flux:input wire:model="cfg.templates.email_from_email" :label="__('From email')" />
                <flux:input type="number" min="1" wire:model="cfg.templates.link_expiry_days" :label="__('Update-link expiry (days)')" />
            </div>
            <div class="flex items-center justify-between rounded-xl border border-[var(--color-hairline)] p-3 text-sm">
                <span>{{ __('Include secure payment-update link') }}</span><flux:switch wire:model="cfg.templates.include_link" />
            </div>
            <flux:textarea wire:model="cfg.templates.sms" :label="__('SMS')" rows="3" />
            <flux:textarea wire:model="cfg.templates.email_body" :label="__('Email body')" rows="4" />
            <flux:textarea wire:model="cfg.templates.voicemail" :label="__('Voicemail script')" rows="3" />
        </div>
    @endif

    @if ($tab === 'destinations')
        <div class="{{ $card }} mt-5 space-y-3">
            <h2 class="{{ $h2 }}">{{ __('Channel Destinations') }}</h2>
            <p class="text-xs text-zinc-500">{{ __('One server-side destination per channel. Secrets belong in the provider, never on this page. Payloads are previewable in Test Mode — no request is made.') }}</p>
            <flux:input wire:model="cfg.destinations.sms_url" :label="__('SMS webhook URL')" placeholder="https://…" />
            <flux:input wire:model="cfg.destinations.email_url" :label="__('Email webhook URL')" placeholder="https://…" />
            <flux:input wire:model="cfg.destinations.voicemail_url" :label="__('Voicemail webhook URL')" placeholder="https://…" />
        </div>
        <div class="{{ $card }} mt-4 space-y-3">
            <h3 class="{{ $h2 }}">{{ __('Lifecycle webhooks') }}</h3>
            <flux:input wire:model="cfg.destinations.lifecycle_failed_url" :label="__('Payment failed event URL')" placeholder="https://…" />
            <flux:input wire:model="cfg.destinations.lifecycle_recovered_url" :label="__('Payment recovered event URL')" placeholder="https://…" />
            <flux:input wire:model="cfg.destinations.lifecycle_resolved_url" :label="__('Payment resolved event URL')" placeholder="https://…" />
        </div>
    @endif

    {{-- =============================================== ACTIVITY =============================================== --}}
    @if ($tab === 'activity')
        <div class="{{ $card }} mt-5">
            <h2 class="{{ $h2 }}">{{ __('Test Activity & Recovery Review') }}</h2>
            <p class="text-xs text-zinc-500">{{ __('Review every pending failed payment and its expected recovery path before Live Mode is enabled.') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Pending failures') }}</div><div class="mt-1 text-2xl font-bold">{{ number_format($activity['pending']) }}</div></div>
                <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Eligible policies') }}</div><div class="mt-1 text-2xl font-bold text-emerald-600">{{ number_format($activity['eligible']) }}</div></div>
                <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Month attempts') }}</div><div class="mt-1 text-2xl font-bold">{{ $activity['month_attempts'] }}</div><div class="text-[11px] text-zinc-400">{{ __('Test Mode forecast only') }}</div></div>
                <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Needs review') }}</div><div class="mt-1 text-2xl font-bold text-amber-600">{{ number_format($activity['needs_review']) }}</div></div>
                <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Pacing') }}</div><div class="mt-1 text-2xl font-bold">{{ $activity['pacing'] }}</div><div class="text-[11px] text-zinc-400">{{ $cfg['hourly_attempt_limit'] }}/hr · {{ $cfg['daily_safety_stop'] }}/day</div></div>
            </div>
        </div>

        <div class="{{ $card }} mt-4">
            <h3 class="{{ $h2 }}">{{ __('Review every failed payment') }}</h3>
            <p class="text-xs text-zinc-500">{{ __('Test Mode projections based on the real pending-failure queue. Nothing here charges a card.') }}</p>
            @foreach (['monthly' => __('Monthly'), 'installment' => __('Installment')] as $bucket => $label)
                <div class="mt-4">
                    <div class="{{ $lbl }} mb-2">{{ $label }} ({{ $activity[$bucket]->count() }})</div>
                    <div class="space-y-3">
                        @forelse ($activity[$bucket] as $p)
                            <div class="rounded-xl border border-[var(--color-hairline)] p-4" wire:key="rehearse-{{ $bucket }}-{{ $p->id }}">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <a href="{{ route('customers.show', $p->id) }}" wire:navigate class="font-semibold text-[var(--color-navy-900)] hover:text-blue-600 dark:text-white">{{ $p->name }}</a>
                                        <span class="ml-2 text-xs text-zinc-400">{{ __('Policy') }} {{ $p->policy_number }}</span>
                                    </div>
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => ! $p->has_profile,
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $p->has_profile,
                                    ])>{{ $p->has_profile ? __('Ready for rehearsal') : __('Needs review') }}</span>
                                </div>
                                <dl class="mt-3 grid gap-3 text-xs sm:grid-cols-4">
                                    <div><dt class="{{ $lbl }}">{{ __('Recovery charge') }}</dt><dd class="font-semibold">{{ $money($p->amount) }}</dd></div>
                                    <div><dt class="{{ $lbl }}">{{ __('Signed up') }}</dt><dd class="font-semibold">{{ $p->signed_up ?? '—' }}</dd></div>
                                    <div><dt class="{{ $lbl }}">{{ __('Payment profile') }}</dt><dd class="font-semibold">{{ $p->anet_customer }}</dd></div>
                                    <div><dt class="{{ $lbl }}">{{ __('Subscription') }}</dt><dd class="font-semibold">{{ $p->subscription }}</dd></div>
                                </dl>
                                <p class="mt-2 text-[11px] text-zinc-400">
                                    {{ $p->has_profile ? __('Eligible for a Test Mode retry rehearsal — no card is charged.') : __('Needs payment update or review before any automatic charge.') }}
                                </p>
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs text-zinc-400">{{ __('No policies in this bucket.') }}</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- =============================================== ANALYTICS =============================================== --}}
    @if ($tab === 'analytics')
        <div class="{{ $card }} mt-5">
            <h2 class="{{ $h2 }}">{{ __('Recovery Analytics') }}</h2>
            <p class="text-xs text-zinc-500">{{ __('Observed retry history only — it does not infer protected financial information.') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Retry attempts') }}</div><div class="mt-1 text-3xl font-bold">{{ number_format($analytics['attempts']) }}</div></div>
                <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Recovered') }}</div><div class="mt-1 text-3xl font-bold text-emerald-600">{{ number_format($analytics['recovered']) }}</div></div>
                <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Success rate') }}</div><div class="mt-1 text-3xl font-bold">{{ $analytics['success_rate'] }}%</div></div>
            </div>
        </div>
        <div class="{{ $card }} mt-4">
            <h3 class="{{ $h2 }}">{{ __('Best observed retry windows') }}</h3>
            @if ($analytics['windows']->isEmpty())
                <p class="mt-4 py-6 text-center text-sm text-zinc-400">{{ __('No retry history is available yet.') }}</p>
            @else
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($analytics['windows'] as $window => $count)
                        <li class="flex items-center justify-between" wire:key="win-{{ $loop->index }}">
                            <span class="font-semibold">{{ $window }}</span>
                            <span class="text-zinc-400">{{ $count }} {{ __('actions') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- =============================================== SAFETY =============================================== --}}
    @if ($tab === 'safety')
        <div class="{{ $card }} mt-5">
            <h2 class="{{ $h2 }}">{{ __('Safety & Live Mode') }}</h2>
            <p class="text-xs text-zinc-500">{{ __('Real charging and real outreach are disabled until an administrator deliberately enables them.') }}</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                @foreach (['test' => ['Test Mode', 'No real charges or outbound messages.'], 'live' => ['Live Mode', 'Requires the billing gate and pause off.'], 'paused' => ['Paused', 'Stops charging and outreach immediately.']] as $m => [$name, $desc])
                    <button type="button"
                        @if ($m === 'live' && ! $liveAvailable) disabled @endif
                        wire:click="$set('cfg.operating_mode', '{{ $m }}')"
                        @class([
                            'rounded-xl border p-4 text-left transition',
                            'border-blue-600 ring-1 ring-blue-600' => ($cfg['operating_mode'] ?? '') === $m,
                            'border-[var(--color-hairline)] hover:border-blue-400' => ($cfg['operating_mode'] ?? '') !== $m,
                            'opacity-50 cursor-not-allowed' => $m === 'live' && ! $liveAvailable,
                        ])>
                        <span class="block text-sm font-semibold">{{ __($name) }}</span>
                        <span class="mt-0.5 block text-[11px] text-zinc-400">{{ __($desc) }}</span>
                    </button>
                @endforeach
            </div>
            @unless ($liveAvailable)
                <flux:callout class="mt-3" variant="secondary" icon="lock-closed">{{ __('Live Mode is unavailable until BILLING_DRIVER and BILLING_PROCESSING_MODE are both set to live.') }}</flux:callout>
            @endunless
        </div>

        <div class="{{ $card }} mt-4">
            <div class="flex items-center justify-between">
                <div><h3 class="{{ $h2 }}">{{ __('Emergency pause') }}</h3><p class="text-xs text-zinc-500">{{ __('A visible kill switch for charging and daily outreach.') }}</p></div>
                <flux:switch wire:model="cfg.emergency_pause" />
            </div>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <flux:input type="number" min="1" wire:model="cfg.hourly_attempt_limit" :label="__('Hourly live-attempt limit')" />
                <flux:input type="number" min="1" wire:model="cfg.daily_safety_stop" :label="__('Daily safety stop')" />
            </div>
        </div>

        <div class="{{ $card }} mt-4 space-y-3">
            <h3 class="{{ $h2 }}">{{ __('Test Mode guarantee') }}</h3>
            <p class="text-xs text-zinc-500">{{ __('Test Mode never calls Authorize.net, sends SMS, sends email, places voicemail calls, or mutates payment, subscription, CRM, or customer records.') }}</p>
            <div class="flex items-center justify-between rounded-xl border border-[var(--color-hairline)] p-3 text-sm">
                <span>{{ __('Smart retries enabled') }}</span><flux:switch wire:model="cfg.smart_retries_enabled" />
            </div>
            <div>
                <div class="{{ $lbl }} mb-1">{{ __('Payment Failed Board — collect mode') }}</div>
                <div class="inline-flex rounded-lg border border-[var(--color-hairline)] p-1 text-sm font-semibold">
                    <button type="button" wire:click="$set('cfg.board_collect_mode', 'always')" @class(['rounded-md px-3 py-1', 'bg-emerald-600 text-white' => ($cfg['board_collect_mode'] ?? '') === 'always', 'text-zinc-500' => ($cfg['board_collect_mode'] ?? '') !== 'always'])>{{ __('Always available') }}</button>
                    <button type="button" wire:click="$set('cfg.board_collect_mode', 'require_charge')" @class(['rounded-md px-3 py-1', 'bg-blue-600 text-white' => ($cfg['board_collect_mode'] ?? '') === 'require_charge', 'text-zinc-500' => ($cfg['board_collect_mode'] ?? '') !== 'require_charge'])>{{ __('Require card charge') }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Shared save bar for the settings tabs --}}
    @if ($isSettings)
        <div class="sticky bottom-4 z-10 mt-5 flex justify-end">
            <flux:button variant="primary" icon="check" wire:click="save" wire:loading.attr="disabled">{{ __('Save changes') }}</flux:button>
        </div>
    @endif
</div>
