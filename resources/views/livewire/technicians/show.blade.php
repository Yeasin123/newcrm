<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-start gap-3">
        <flux:button icon="arrow-left" variant="ghost" size="sm" :href="route('technicians.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $technician->full_name }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ $technician->business_name }} &middot; {{ $technician->contact ?: '—' }} &middot; {{ $technician->email }}
            </flux:text>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        @foreach ([
            ['Total claims', $technician->claims()->count()],
            ['Open claims', $openCount],
            ['Call fee', $technician->call_fee ? '$'.number_format((float) $technician->call_fee, 2) : '—'],
            ['Avg rating', $avgStars ? $avgStars.' ★' : '—'],
        ] as [$label, $value])
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="s-{{ $label }}">
                <flux:text class="text-sm text-zinc-500">{{ __($label) }}</flux:text>
                <div class="mt-1 text-xl font-semibold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:heading size="lg">{{ __('Coverage') }}</flux:heading>
            <dl class="space-y-2 text-sm">
                <x-customer-field :label="__('Address')" :value="$technician->address" />
                <x-customer-field :label="__('Zip')" :value="$technician->zip_code" />
                <x-customer-field :label="__('States')" :value="collect($technician->state)->flatten()->filter()->implode(', ')" />
                <x-customer-field :label="__('Cities')" :value="collect($technician->city)->flatten()->filter()->implode(', ')" />
                <x-customer-field :label="__('Services')" :value="collect($technician->services)->flatten()->filter()->implode(', ')" />
            </dl>
        </flux:card>

        <flux:card class="lg:col-span-2">
            <flux:heading size="lg" class="mb-3">{{ __('Recent claims') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Claim #') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Total') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($claims as $claim)
                        <flux:table.row :key="'c-'.$claim->id" class="cursor-pointer" wire:navigate href="{{ route('claims.show', $claim) }}">
                            <flux:table.cell variant="strong">{{ $claim->claim_no ?: $claim->id }}</flux:table.cell>
                            <flux:table.cell>{{ $claim->client ? trim($claim->client->first_name.' '.$claim->client->last_name) : '—' }}</flux:table.cell>
                            <flux:table.cell>${{ number_format((float) $claim->total_price, 2) }}</flux:table.cell>
                            <flux:table.cell>
                                @php($cs = \App\Enums\ClaimStatus::tryFrom((int) $claim->status))
                                <flux:badge size="sm" :color="$cs?->color() ?? 'zinc'" inset="top bottom">{{ $cs?->label() ?? $claim->status }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="4" class="text-center text-zinc-500">{{ __('No claims yet.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    @if ($ratings->isNotEmpty())
        <flux:card>
            <flux:heading size="lg" class="mb-3">{{ __('Ratings') }}</flux:heading>
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-700">
                @foreach ($ratings as $r)
                    <li class="py-2" wire:key="r-{{ $r->id }}">
                        <span class="font-medium">{{ str_repeat('★', (int) $r->stars) }}{{ str_repeat('☆', max(0, 5 - (int) $r->stars)) }}</span>
                        <span class="text-zinc-500">{{ $r->notes }}</span>
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @endif
</div>
