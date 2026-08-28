<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div>
        <flux:heading size="xl">{{ __('Entrance Blast') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            {{ __('Credentials, message groups and pacing for the Entrance Group SMS blast. The blast itself runs from the Entrance Group console.') }}
        </flux:text>
    </div>

    {{-- slots --}}
    <flux:card class="p-5">
        <flux:heading size="sm">{{ __('Entrance slots') }}</flux:heading>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            @foreach ($slots as $userId => $slot)
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="slot-{{ $userId }}">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ __('Slot') }} {{ $userId }}</span>
                        <flux:badge size="sm" :color="$slot['configured'] ? 'green' : 'zinc'">
                            {{ $slot['configured'] ? __('Configured') : __('Empty') }}
                        </flux:badge>
                    </div>
                    @if ($slot['configured'])
                        <flux:button size="xs" variant="ghost" class="mt-2" wire:click="clearSlot({{ $userId }})"
                            wire:confirm="{{ __('Clear this slot?') }}">{{ __('Clear') }}</flux:button>
                    @endif
                </div>
            @endforeach
        </div>

        <form wire:submit="saveSlot" class="mt-4 grid gap-3 sm:grid-cols-2">
            <flux:select wire:model="slotUserId" :label="__('Slot')">
                @foreach ([4055, 4479, 4480] as $id)
                    <flux:select.option :value="$id">{{ $id }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="clientId" :label="__('Client ID')" />
            <flux:input wire:model="accessToken" :label="__('Access token')" />
            <flux:input wire:model="refreshToken" :label="__('Refresh token')" />
            <div class="sm:col-span-2">
                <flux:button type="submit" variant="primary">{{ __('Save slot') }}</flux:button>
            </div>
        </form>
    </flux:card>

    {{-- message groups --}}
    <flux:card class="p-5">
        <flux:heading size="sm">{{ __('Message groups') }}</flux:heading>

        <form wire:submit="createGroup" class="mt-3 grid gap-3">
            <flux:input wire:model="grpName" :label="__('Group name')" />
            <flux:textarea wire:model="grpText" rows="4" :label="__('Messages (one per line)')" />
            <div><flux:button type="submit" variant="primary">{{ __('Create group') }}</flux:button></div>
        </form>

        <div class="mt-4 flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($groups as $group)
                <div class="py-3" wire:key="grp-{{ $group['unique_grp_id'] }}">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ $group['grp_name'] }}</span>
                        <div class="flex items-center gap-1">
                            @foreach ([4055, 4479, 4480] as $id)
                                <flux:button size="xs" variant="ghost"
                                    wire:click="attachGroup({{ $id }}, '{{ $group['unique_grp_id'] }}')">→ {{ $id }}</flux:button>
                            @endforeach
                            <flux:button size="xs" variant="ghost" wire:click="deleteGroup('{{ $group['unique_grp_id'] }}')"
                                wire:confirm="{{ __('Delete this group?') }}">{{ __('Delete') }}</flux:button>
                        </div>
                    </div>
                    <ul class="mt-1 list-disc pl-5 text-sm text-zinc-500">
                        @foreach ($group['lines'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="py-3 text-sm text-zinc-500">{{ __('No message groups yet.') }}</div>
            @endforelse
        </div>
    </flux:card>

    {{-- pacing --}}
    <flux:card class="p-5">
        <flux:heading size="sm">{{ __('Pacing') }}</flux:heading>
        <form wire:submit="saveSettings" class="mt-3 grid gap-3 sm:grid-cols-2">
            <flux:input type="number" step="0.1" wire:model="delayMinutes" :label="__('Delay between sends (minutes)')" />
            <flux:input type="number" wire:model="campaignSplit" :label="__('Campaign split')" />
            <div class="sm:col-span-2"><flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button></div>
        </form>
    </flux:card>
</div>
