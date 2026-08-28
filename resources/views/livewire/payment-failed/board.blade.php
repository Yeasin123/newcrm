<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Payment Failed') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ __('Customer :n of :total', ['n' => min($i + 1, $total), 'total' => number_format($total)]) }}
            </flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="salesman" :placeholder="__('All salesmen')" class="max-w-48">
                <flux:select.option value="">{{ __('All salesmen') }}</flux:select.option>
                @foreach ($salesmen as $s)
                    <flux:select.option value="{{ $s }}" wire:key="sm-{{ $loop->index }}">{{ $s }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="prev" :disabled="$i === 0" />
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="next" :disabled="$i + 1 >= $total" />
        </div>
    </div>

    @if (! $client)
        <flux:callout icon="check-circle" variant="success">{{ __('No failed-payment customers in the queue. ') }}</flux:callout>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <flux:card class="space-y-2 lg:col-span-1">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ $client->full_name }}</flux:heading>
                    <flux:button size="xs" variant="ghost" icon="arrow-top-right-on-square" :href="route('customers.show', $client)" wire:navigate />
                </div>
                <dl class="space-y-2 text-sm">
                    <x-customer-field :label="__('Policy #')" :value="$client->policy_number" />
                    <x-customer-field :label="__('Phone')" :value="$client->contact" />
                    <x-customer-field :label="__('Email')" :value="$client->email" />
                    <x-customer-field :label="__('Plan')" :value="$client->propertyPackage->title ?? null" />
                    <x-customer-field :label="__('Failed on')" :value="optional($client->payment_faild_date)->format('M j, Y') ?? null" />
                    <x-customer-field :label="__('Salesman')" :value="$client->salesman_name" />
                </dl>
                <flux:button size="sm" variant="primary" class="w-full" wire:click="markRecovered">{{ __('Mark recovered') }}</flux:button>
            </flux:card>

            <flux:card class="space-y-3 lg:col-span-2">
                <flux:heading size="lg">{{ __('Work this account') }}</flux:heading>

                <div class="flex flex-wrap gap-2">
                    @foreach (['click_to_call' => __('Call'), 'send_sms' => __('Text'), 'send_mail' => __('Email'), 'voice' => __('Voicemail')] as $act => $label)
                        <flux:button size="sm" variant="ghost" wire:click="logAction('{{ $act }}')">{{ $label }}</flux:button>
                    @endforeach
                </div>

                <flux:select wire:model="statusLabel" :label="__('Set status')">
                    <flux:select.option value="">{{ __('— pick —') }}</flux:select.option>
                    @foreach ($statuses as $st)
                        <flux:select.option value="{{ $st }}" wire:key="st-{{ $loop->index }}">{{ $st }}</flux:select.option>
                    @endforeach
                </flux:select>

                <form wire:submit="saveNote" class="flex items-start gap-2">
                    <flux:textarea wire:model="noteBody" rows="2" class="flex-1" :placeholder="__('Board note…')" />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </form>
                @error('noteBody') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror

                <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-700">
                    @forelse ($client->buttonTracks->take(15) as $bt)
                        <li class="py-2" wire:key="bt-{{ $bt->id }}">
                            <span class="font-medium">{{ $bt->button_text }}</span>
                            <span class="text-zinc-500">— {{ $bt->comments }}</span>
                            <span class="text-xs text-zinc-400">{{ $bt->user?->name }} · {{ $bt->create_date }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-zinc-500">{{ __('No board activity yet.') }}</li>
                    @endforelse
                </ul>
            </flux:card>
        </div>
    @endif
</div>
