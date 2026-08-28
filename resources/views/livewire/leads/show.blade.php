<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <flux:button icon="arrow-left" variant="ghost" size="sm" :href="route('leads.index')" wire:navigate />
            <div>
                <flux:heading size="xl">{{ trim($lead->first_name.' '.$lead->last_name) ?: '—' }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500">
                    {{ $lead->campaign->name ?? __('No campaign') }}
                    &middot; {{ __('Created') }} {{ optional($lead->created_at)->format('M j, Y') ?? '—' }}
                </flux:text>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($onSaleSheet)
                <flux:badge color="green">{{ __('On Sale Sheet') }}</flux:badge>
            @else
                <flux:button size="sm" variant="primary" wire:click="sendToSaleSheet">{{ __('Send to Sale Sheet') }}</flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Contact') }}</flux:heading>
            <dl class="space-y-2 text-sm">
                <x-customer-field :label="__('Phone')" :value="$lead->contact" />
                <x-customer-field :label="__('Email')" :value="$lead->email" />
                <x-customer-field :label="__('Address')" :value="$lead->address" />
                <x-customer-field :label="__('City / State / Zip')" :value="trim(collect([$lead->city, $lead->state, $lead->zip_code])->filter()->implode(', '))" />
                <x-customer-field :label="__('Carrier')" :value="$lead->phone_company" />
                <x-customer-field :label="__('Phone type')" :value="$lead->contact_text" />
            </dl>
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Pipeline') }}</flux:heading>
            <form wire:submit="updateStatus" class="flex items-end gap-2">
                <flux:select wire:model="statusId" :label="__('Status')" class="flex-1">
                    @foreach ($statuses as $id => $title)
                        <flux:select.option value="{{ $id }}" wire:key="s-{{ $id }}">{{ $title }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" size="sm">{{ __('Set') }}</flux:button>
            </form>
            <form wire:submit="assignRep" class="flex items-end gap-2">
                <flux:select wire:model="assignRepId" :label="__('Assigned rep')" class="flex-1">
                    <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                    @foreach ($reps as $rep)
                        <flux:select.option value="{{ $rep->id }}" wire:key="r-{{ $rep->id }}">{{ $rep->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" size="sm">{{ __('Assign') }}</flux:button>
            </form>
            <div class="text-sm text-zinc-500">
                {{ __('Salesman') }}: {{ $lead->salesman?->name ?? '—' }}
            </div>
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Reminders') }}</flux:heading>
            <form wire:submit="addReminder" class="space-y-2">
                <div class="flex gap-2">
                    <flux:input type="date" wire:model="reminderDate" class="flex-1" />
                    <flux:input type="time" wire:model="reminderTime" class="w-32" />
                </div>
                <flux:input wire:model="reminderNote" :placeholder="__('Note (optional)')" />
                <flux:button type="submit" size="sm" variant="primary" class="w-full">{{ __('Add reminder') }}</flux:button>
            </form>
            @error('reminderDate') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-700">
                @forelse ($lead->reminders as $r)
                    <li class="py-2" wire:key="rem-{{ $r->id }}">
                        <span class="font-medium">{{ $r->date }} {{ $r->time }}</span>
                        <span class="text-zinc-500">— {{ $r->description ?: __('(no note)') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-zinc-500">{{ __('No reminders.') }}</li>
                @endforelse
            </ul>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Notes') }}</flux:heading>
        <form wire:submit="addNote" class="flex items-start gap-2">
            <flux:textarea wire:model="noteBody" rows="2" class="flex-1" :placeholder="__('Add a note…')" />
            <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
        </form>
        @error('noteBody') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-700">
            @forelse ($lead->leadNotes as $note)
                <li class="py-3" wire:key="n-{{ $note->id }}">
                    <div class="text-sm whitespace-pre-line">{{ $note->notes }}</div>
                    <div class="mt-1 text-xs text-zinc-500">{{ $note->author?->name ?? __('System') }} &middot; {{ $note->date_time }}</div>
                </li>
            @empty
                <li class="py-3 text-sm text-zinc-500">{{ __('No notes yet.') }}</li>
            @endforelse
        </ul>
    </flux:card>
</div>
