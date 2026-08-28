<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Webhook Report') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Inbound webhook activity per day.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button size="sm" :variant="$view === 'daily' ? 'primary' : 'ghost'" wire:click="$set('view', 'daily')">{{ __('Source counts') }}</flux:button>
            <flux:button size="sm" :variant="$view === 'entrance' ? 'primary' : 'ghost'" wire:click="$set('view', 'entrance')">{{ __('Entrance "yes" attribution') }}</flux:button>
        </div>
    </div>

    @if ($view === 'entrance')
        <flux:card class="overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left dark:border-zinc-700">
                        <th class="p-3">{{ __('Date') }}</th>
                        <th class="p-3 text-center">{{ __('Yes') }}</th>
                        @foreach ($entranceLabels as $label)
                            <th class="p-3 text-center">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entranceRows as $row)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800" wire:key="er-{{ $row['day'] }}">
                            <td class="p-3 font-medium">{{ $row['day'] }}</td>
                            <td class="p-3 text-center font-semibold">{{ number_format($row['total_yes']) }}</td>
                            @foreach ($entranceLabels as $label)
                                @php $c = $row['channels'][$label] ?? ['yes' => 0, 'sale' => 0, 'sale_price' => 0]; @endphp
                                <td class="p-3 text-center {{ $c['yes'] === 0 ? 'text-zinc-300' : '' }}">
                                    {{ $c['yes'] }}
                                    @if ($c['sale'] > 0)
                                        <span class="block text-[11px] text-green-600">{{ $c['sale'] }} sale · ${{ number_format($c['sale_price'], 0) }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($entranceLabels) + 2 }}" class="p-6 text-center text-zinc-500">{{ __('No entrance replies matched.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </flux:card>
        @if ($entranceRows)
            <div>{{ $entranceRows->links() }}</div>
        @endif
    @else
    <flux:card class="overflow-x-auto p-0">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-200 text-left dark:border-zinc-700">
                    <th class="p-3">{{ __('Date') }}</th>
                    @foreach ($sources as $src)
                        <th class="p-3 text-center">{{ $src }}</th>
                    @endforeach
                    <th class="p-3 text-center">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-zinc-100 dark:border-zinc-800" wire:key="d-{{ $row->date }}">
                        <td class="p-3 font-medium">{{ $row->date }}</td>
                        @foreach ($sources as $src)
                            @php $count = (int) ($row->{$src.'_count'} ?? 0); @endphp
                            <td class="p-3 text-center">
                                @if ($count > 0)
                                    <button type="button" class="text-blue-600 hover:underline"
                                        wire:click="showDetail('{{ $row->date }}', '{{ $src }}')">{{ $count }}</button>
                                @else
                                    <span class="text-zinc-300">0</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="p-3 text-center font-semibold">{{ number_format($row->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($sources) + 2 }}" class="p-6 text-center text-zinc-500">{{ __('No webhook activity recorded.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </flux:card>

    @if ($detail !== null)
        <flux:card class="p-5">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ $detailType }} — {{ $detailDate }}</flux:heading>
                <flux:button size="xs" variant="ghost" wire:click="closeDetail">{{ __('Close') }}</flux:button>
            </div>
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('Lead') }}</flux:table.column>
                    <flux:table.column>{{ __('Contact') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('When') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($detail as $w)
                        <flux:table.row :key="'wd-'.$w->id">
                            <flux:table.cell variant="strong">
                                @if ($w->leadDetail)
                                    <a class="hover:underline" wire:navigate href="{{ route('leads.show', $w->leadDetail) }}">
                                        {{ trim($w->leadDetail->first_name.' '.$w->leadDetail->last_name) ?: __('Lead #').$w->lead_id }}
                                    </a>
                                @else
                                    {{ __('Lead #').$w->lead_id }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $w->leadDetail?->contact ?: '—' }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $w->leadDetail?->email ?: '—' }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ optional($w->STORE_DATE)->format('H:i') }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="4" class="text-center text-zinc-500">{{ __('No rows.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
    @endif
</div>
