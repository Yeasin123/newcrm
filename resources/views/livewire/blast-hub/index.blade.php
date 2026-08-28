<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div>
        <flux:heading size="xl">{{ __('Blast Hub') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            {{ __('Generate a filtered, de-duplicated lead list. Downloading a run locks its leads out of future runs for 28 days.') }}
        </flux:text>
    </div>

    <flux:card class="p-5">
        <form wire:submit="generate" class="grid gap-4 md:grid-cols-3">
            <flux:select wire:model="blastType" :label="__('Blast type')">
                <flux:select.option value="cold">{{ __('Cold — no status') }}</flux:select.option>
                <flux:select.option value="warm">{{ __('Warm — no answer / callback') }}</flux:select.option>
                <flux:select.option value="hot">{{ __('Hot — interested / qualified') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model="carrierFilter" :label="__('Carrier')">
                <flux:select.option value="all">{{ __('All carriers') }}</flux:select.option>
                <flux:select.option value="exclude_tmobile">{{ __('Exclude T-Mobile') }}</flux:select.option>
                <flux:select.option value="tmobile_only">{{ __('T-Mobile only') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model="leadCount" :label="__('Max leads')">
                @foreach (['50000', '100000', '200000', '500000', 'all'] as $c)
                    <flux:select.option :value="$c">{{ $c === 'all' ? __('All eligible') : number_format((int) $c) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="minAiScore" :label="__('Min AI score')">
                <flux:select.option value="any">{{ __('Any') }}</flux:select.option>
                <flux:select.option value="25">25+</flux:select.option>
                <flux:select.option value="50">50+</flux:select.option>
                <flux:select.option value="75">75+</flux:select.option>
            </flux:select>

            <flux:select wire:model="campaignId" :label="__('Campaign (optional)')">
                <flux:select.option :value="null">{{ __('All campaigns') }}</flux:select.option>
                @foreach ($campaigns as $campaign)
                    <flux:select.option :value="$campaign->id">{{ $campaign->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-end">
                <flux:switch wire:model="dncExcluded" :label="__('Exclude DNC numbers')" />
            </div>

            <div class="md:col-span-3">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Generate run') }}
                </flux:button>
            </div>
        </form>
    </flux:card>

    @if ($latestRun)
        <flux:card class="p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="sm">
                    {{ __('Run #:id', ['id' => $latestRun->id]) }}
                    <flux:badge size="sm" :color="$latestRun->status === 'downloaded' ? 'zinc' : 'green'">
                        {{ ucfirst($latestRun->status) }}
                    </flux:badge>
                </flux:heading>
                <flux:button size="sm" icon="arrow-down-tray" :href="route('blast-hub.download', $latestRun)">
                    {{ __('Download CSV') }}
                </flux:button>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div><dt class="text-zinc-500">{{ __('Selected') }}</dt><dd class="text-lg font-semibold">{{ number_format($latestRun->selected_count) }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Eligible') }}</dt><dd>{{ number_format($latestRun->total_eligible) }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Excluded — non-cell') }}</dt><dd>{{ number_format($latestRun->excluded_non_cell_count) }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Excluded — locked') }}</dt><dd>{{ number_format($latestRun->excluded_locked_count) }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Excluded — DNC') }}</dt><dd>{{ number_format($latestRun->excluded_dnc_count) }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Type') }}</dt><dd>{{ ucfirst($latestRun->blast_type) }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Carrier') }}</dt><dd>{{ str_replace('_', ' ', $latestRun->carrier_filter) }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Campaign') }}</dt><dd>{{ $latestRun->campaign?->name ?: __('All') }}</dd></div>
            </dl>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>{{ __('Lead') }}</flux:table.column>
                    <flux:table.column>{{ __('Phone') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('AI score') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($latestRun->items as $item)
                        <flux:table.row :key="'i-'.$item->id">
                            <flux:table.cell variant="strong">
                                {{ trim(($item->lead?->first_name ?? '').' '.($item->lead?->last_name ?? '')) ?: __('Lead #').$item->lead_id }}
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $item->phone_normalized ?: $item->lead?->contact }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $item->lead?->email ?: '—' }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $item->ai_score ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-zinc-500">{{ __('No leads selected.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            @if ($latestRun->selected_count > $latestRun->items->count())
                <flux:text class="mt-2 text-xs text-zinc-500">
                    {{ __('Showing the first :n of :total.', ['n' => $latestRun->items->count(), 'total' => number_format($latestRun->selected_count)]) }}
                </flux:text>
            @endif
        </flux:card>
    @endif

    <flux:card class="p-5">
        <flux:heading size="sm">{{ __('Recent runs') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>#</flux:table.column>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Selected') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($runs as $run)
                    <flux:table.row :key="'r-'.$run->id">
                        <flux:table.cell variant="strong">{{ $run->id }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ optional($run->created_at)->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ ucfirst($run->blast_type) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($run->selected_count) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$run->status === 'downloaded' ? 'zinc' : 'green'">{{ ucfirst($run->status) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="ghost" icon="arrow-down-tray" :href="route('blast-hub.download', $run)">
                                {{ __('CSV') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">{{ __('No runs yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
