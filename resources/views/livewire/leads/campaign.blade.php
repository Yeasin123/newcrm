@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-4';
    $btn = 'inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5';
@endphp

<div class="w-full">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <a href="{{ route('leads.index') }}" wire:navigate class="mt-1 inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-blue-600">
                <flux:icon name="arrow-left" variant="micro" class="size-4" /> {{ __('Campaigns') }}
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ $campaign->name ?: '#'.$campaign->id }}</h1>
                    <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">{{ $campaign->price_type ?: __('campaign') }}</span>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => (int) $campaign->status === 1,
                        'bg-zinc-200 text-zinc-600 dark:bg-white/10 dark:text-zinc-300' => (int) $campaign->status !== 1,
                    ])>{{ (int) $campaign->status === 1 ? __('Active') : __('Inactive') }}</span>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="openEdit" class="{{ $btn }}">
                <flux:icon name="pencil-square" variant="micro" class="size-4" /> {{ __('Edit') }}
            </button>
            <button type="button" wire:click="$set('showImport', true)" class="{{ $btn }} border-emerald-300 text-emerald-600 dark:border-emerald-500/40">
                <flux:icon name="arrow-up-tray" variant="micro" class="size-4" /> {{ __('Import CSV') }}
            </button>
            <a href="{{ route('leads.campaign.export', ['campaign' => $campaign, 'tag' => $tag, 'status' => $status, 'search' => $search]) }}" class="{{ $btn }} border-blue-300 text-blue-600 dark:border-blue-500/40">
                <flux:icon name="arrow-down-tray" variant="micro" class="size-4" /> {{ __('Download') }}
            </a>
            <button type="button" wire:click="$set('showScores', true)" class="{{ $btn }} border-violet-300 text-violet-600 dark:border-violet-500/40">
                <flux:icon name="sparkles" variant="micro" class="size-4" /> {{ __('AI Scores') }}
            </button>
            <button type="button" wire:click="markAllInquiry" wire:confirm="{{ __('Tag every untagged lead in this campaign as Inquiry?') }}" class="{{ $btn }} border-red-300 text-red-600 dark:border-red-500/40">
                <flux:icon name="tag" variant="micro" class="size-4" /> {{ __('Mark All Inquiry') }}
            </button>
            <button type="button" wire:click="$set('showAdd', true)" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-blue-700">
                <flux:icon name="plus" variant="micro" class="size-4" /> {{ __('Add Lead') }}
            </button>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8">
        @foreach ([
            ['Total', number_format($kpi['total']), 'users', 'text-blue-600'],
            ['Sale', number_format($kpi['sale']), 'check-circle', 'text-emerald-600'],
            ['Call Back', number_format($kpi['call_back']), 'clock', 'text-violet-600'],
            ['No Answer', number_format($kpi['no_answer']), 'phone-x-mark', 'text-amber-600'],
            ['Spoke With', number_format($kpi['spoke_with'] ?? 0), 'chat-bubble-left-right', 'text-sky-600'],
            ['Doobie', number_format($kpi['doobie'] ?? 0), 'sparkles', 'text-pink-600'],
            ['No Status', number_format($kpi['no_status']), 'exclamation-circle', 'text-zinc-400'],
            ['Conv. %', $kpi['conv'].'%', 'chart-bar', 'text-emerald-600'],
        ] as [$label, $value, $icon, $tone])
            <div class="{{ $card }}">
                <div class="flex items-center justify-between text-[10px] font-semibold uppercase tracking-wide text-zinc-400">
                    <span>{{ __($label) }}</span>
                    <flux:icon name="{{ $icon }}" variant="micro" class="size-3.5 {{ $tone }}" />
                </div>
                <div class="mt-1 text-xl font-bold {{ $tone }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    {{-- Webhook URL --}}
    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm dark:border-blue-500/30 dark:bg-blue-500/10"
        x-data="{ copied: false }">
        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">
            <flux:icon name="link" variant="micro" class="size-4" /> {{ __('Webhook URL') }}
        </span>
        <code class="min-w-0 flex-1 truncate rounded-lg bg-[var(--color-surface)] px-3 py-1.5 font-mono text-xs text-zinc-600 dark:bg-[var(--color-surface-2)] dark:text-zinc-300">{{ $this->webhookUrl() }}</code>
        <button type="button" x-on:click="navigator.clipboard.writeText('{{ $this->webhookUrl() }}'); copied = true; setTimeout(() => copied = false, 1500)"
            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
            <flux:icon name="clipboard-document" variant="micro" class="size-3.5" />
            <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'"></span>
        </button>
    </div>

    {{-- Filters --}}
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <div class="inline-flex flex-wrap rounded-lg border border-[var(--color-hairline)] p-0.5 text-sm font-medium">
            @foreach ([
                '' => ['All', ''],
                'inquiry' => ['Inquiry (Hot)', '🔥'],
                'sms' => ['SMS (Warm)', '🏷'],
                'none' => ['No Tag (Cold)', '❄'],
            ] as $key => [$label, $emoji])
                <button type="button" wire:click="setTag('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition',
                        'bg-blue-600 text-white' => $tag === $key,
                        'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tag !== $key,
                    ])>{{ $emoji }} {{ __($label) }}</button>
            @endforeach
        </div>
        <select wire:model.live="status" class="rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
            <option value="">{{ __('All Statuses') }}</option>
            @foreach ($statuses as $id => $title)
                <option value="{{ $id }}">{{ $title }}</option>
            @endforeach
        </select>
        <div class="relative max-w-xs flex-1">
            <flux:icon name="magnifying-glass" variant="outline" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="text" wire:model.live.debounce.350ms="search" placeholder="{{ __('Search name, phone, email…') }}"
                class="w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] py-2 pl-9 pr-3 text-sm placeholder:text-zinc-400 focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
        </div>
    </div>

    {{-- Lead list --}}
    <div class="mt-4 {{ $card }} !p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[880px] text-sm">
                <thead class="border-b border-[var(--color-hairline)] text-left">
                    <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                        <th>{{ __('Lead') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Email') }}</th><th>{{ __('Status') }}</th><th>{{ __('Tag') }}</th><th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-hairline)]" wire:loading.class="opacity-50" wire:target="tag,status,search,setTag">
                    @forelse ($leads as $l)
                        <tr wire:key="lead-{{ $l->id }}" class="[&>td]:px-4 [&>td]:py-3">
                            <td>
                                <a href="{{ route('leads.show', $l->id) }}" wire:navigate class="font-semibold text-[var(--color-navy-900)] hover:text-blue-600 dark:text-zinc-100">{{ trim($l->first_name.' '.$l->last_name) ?: '—' }}</a>
                                <div class="text-xs text-zinc-400">{{ trim(collect([$l->city, $l->state])->filter()->implode(', ')) ?: optional($l->created_at)->format('m/d/Y') }}</div>
                            </td>
                            <td class="whitespace-nowrap">
                                @if ($l->contact)<a href="tel:{{ $l->contact }}" class="text-blue-600 underline decoration-blue-600/30">{{ $l->contact }}</a>@else — @endif
                            </td>
                            <td class="text-zinc-600 dark:text-zinc-300">{{ $l->email ?: '—' }}</td>
                            <td>
                                @if ($l->leadStatus?->title)
                                    <span class="rounded-full bg-black/5 px-2.5 py-1 text-xs font-semibold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ $l->leadStatus->title }}</span>
                                @else <span class="rounded-full bg-zinc-200 px-2.5 py-1 text-xs font-semibold text-zinc-500 dark:bg-white/10">{{ __('No Status') }}</span> @endif
                            </td>
                            <td>
                                @if ($l->lead_tag)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ $l->lead_tag }}</span>
                                @else <span class="text-zinc-400">—</span> @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('sale-sheet.index', ['lead' => $l->id]) }}" target="_blank" rel="noopener"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                    <flux:icon name="user-plus" variant="micro" class="size-4" /> {{ __('Convert') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-16 text-center text-sm text-zinc-500">{{ __('No leads match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($hasMore)
            <div wire:key="more-{{ $perPage }}" x-data x-init="new IntersectionObserver(([e]) => e.isIntersecting && $wire.loadMore(), { rootMargin: '500px' }).observe($el)"
                class="border-t border-[var(--color-hairline)] py-4 text-center text-sm text-zinc-400">
                <span wire:loading.remove wire:target="loadMore">{{ __('Scroll for more…') }}</span>
                <span wire:loading wire:target="loadMore">{{ __('Loading…') }}</span>
            </div>
        @elseif ($atCap)
            <p class="border-t border-[var(--color-hairline)] py-4 text-center text-xs text-zinc-400">{{ __('Showing the first :n. Narrow with a filter or search.', ['n' => number_format($perPage)]) }}</p>
        @endif
    </div>

    {{-- Add Lead modal --}}
    <flux:modal wire:model="showAdd" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Add Lead to :name', ['name' => $campaign->name]) }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="lead.first_name" :label="__('First Name')" />
                <flux:input wire:model="lead.last_name" :label="__('Last Name')" />
                <flux:input wire:model="lead.contact" :label="__('Phone')" />
                <flux:input wire:model="lead.email" type="email" :label="__('Email')" />
                <flux:input wire:model="lead.address" :label="__('Street')" class="sm:col-span-2" />
                <flux:input wire:model="lead.city" :label="__('City')" />
                <flux:input wire:model="lead.state" :label="__('State')" />
                <flux:input wire:model="lead.zip_code" :label="__('Zip')" />
            </div>
            @error('lead.first_name') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            @error('lead.contact') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showAdd', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="addLead">{{ __('Add Lead') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Edit Campaign modal --}}
    <flux:modal wire:model="showEdit" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Edit Campaign') }}</flux:heading>
            <flux:input wire:model="ceName" :label="__('Campaign name')" />
            <flux:input wire:model="ceType" :label="__('Type')" placeholder="cold_blast" />
            <flux:select wire:model="ceStatus" :label="__('Status')">
                <flux:select.option value="1">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="0">{{ __('Inactive') }}</flux:select.option>
                <flux:select.option value="2">{{ __('Pending') }}</flux:select.option>
            </flux:select>
            @error('ceName') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showEdit', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveCampaign">{{ __('Save Changes') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Import CSV modal --}}
    <flux:modal wire:model="showImport" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Import Leads into :name', ['name' => $campaign->name]) }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('CSV with a header row. Columns that match a lead field (first_name, last_name, contact, email, address, city, state, zip_code…) are imported into this campaign.') }}</flux:text>

            @if ($imported !== null)
                <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                    {{ __('Imported :n lead(s).', ['n' => number_format($imported)]) }}
                    @if ($skipped) {{ __(':n row(s) skipped (no name/phone/email).', ['n' => number_format($skipped)]) }} @endif
                </div>
            @endif
            @if ($importError)
                <div class="rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">{{ $importError }}</div>
            @endif

            <input type="file" wire:model="importFile" accept=".csv,text/csv"
                class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-700 dark:text-zinc-300">
            @error('importFile') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div wire:loading wire:target="importFile" class="text-sm text-zinc-500">{{ __('Reading file…') }}</div>

            @if ($importPreview)
                <div class="rounded-lg bg-black/[0.03] p-3 text-sm dark:bg-white/5">
                    <p class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('Detected columns:') }}</p>
                    <p class="mt-1 text-zinc-500">{{ implode(', ', $importPreview) }}</p>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showImport', false)">{{ __('Close') }}</flux:button>
                <flux:button variant="primary" wire:click="importCsv" wire:loading.attr="disabled" wire:target="importCsv">
                    <span wire:loading.remove wire:target="importCsv">{{ __('Import') }}</span>
                    <span wire:loading wire:target="importCsv">{{ __('Importing…') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- AI Scores modal --}}
    <flux:modal wire:model="showScores" class="max-w-md">
        @php($sc = $this->scoreSummary)
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('AI Lead Scores') }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ $sc['ai_enabled']
                    ? __('Model scoring runs in Blast Hub. Here you can fill gaps with a fast status heuristic.')
                    : __('AI is off (Control Center) — scoring uses a status heuristic only.') }}
            </flux:text>

            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg border border-[var(--color-hairline)] p-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Scored') }}</div>
                    <div class="mt-1 text-xl font-bold text-[var(--color-navy-900)] dark:text-zinc-100">{{ number_format($sc['scored']) }} <span class="text-xs font-normal text-zinc-400">/ {{ number_format($kpi['total']) }}</span></div>
                </div>
                <div class="rounded-lg border border-[var(--color-hairline)] p-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Avg score') }}</div>
                    <div class="mt-1 text-xl font-bold text-violet-600">{{ $sc['avg'] }}</div>
                </div>
            </div>
            <div class="flex gap-2 text-xs font-semibold">
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ number_format($sc['hot']) }} {{ __('hot') }}</span>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ number_format($sc['warm']) }} {{ __('warm') }}</span>
                <span class="rounded-full bg-zinc-200 px-2.5 py-1 text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ number_format($sc['cold']) }} {{ __('cold') }}</span>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showScores', false)">{{ __('Close') }}</flux:button>
                <flux:button variant="primary" wire:click="generateScores" wire:loading.attr="disabled" wire:target="generateScores">
                    <span wire:loading.remove wire:target="generateScores">{{ __('Score unscored leads') }}</span>
                    <span wire:loading wire:target="generateScores">{{ __('Scoring…') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
