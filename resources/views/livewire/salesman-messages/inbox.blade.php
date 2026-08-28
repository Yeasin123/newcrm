<div class="flex h-full w-full flex-1 flex-col gap-4">
    <div>
        <flux:heading size="xl">{{ __('Salesman Messages') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Two-way SMS with your sales reps.') }}</flux:text>
    </div>

    <div class="grid flex-1 gap-4 lg:grid-cols-[18rem_1fr]">
        {{-- rep list --}}
        <flux:card class="flex flex-col overflow-hidden p-0">
            <div class="max-h-[70vh] divide-y divide-zinc-200 overflow-y-auto dark:divide-zinc-700">
                @forelse ($salesmen as $rep)
                    @php($name = trim($rep->first_name.' '.$rep->last_name) ?: $rep->email)
                    <button type="button" wire:key="rep-{{ $rep->id }}" wire:click="selectSalesman({{ $rep->id }})"
                        class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm hover:bg-zinc-50 dark:hover:bg-white/5 {{ $selected?->id === $rep->id ? 'bg-zinc-100 dark:bg-white/10' : '' }}">
                        <span>
                            <span class="font-medium">{{ $name }}</span>
                            <span class="block text-xs text-zinc-500">{{ $rep->contact ?: __('No phone') }}</span>
                        </span>
                        @if (($unreadCounts[$rep->id] ?? 0) > 0)
                            <flux:badge size="sm" color="red">{{ $unreadCounts[$rep->id] }}</flux:badge>
                        @endif
                    </button>
                @empty
                    <div class="px-4 py-3 text-sm text-zinc-500">{{ __('No active salesmen.') }}</div>
                @endforelse
            </div>
        </flux:card>

        {{-- conversation --}}
        <flux:card class="flex flex-col p-0">
            @if ($selected)
                <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <flux:heading size="sm">{{ trim($selected->first_name.' '.$selected->last_name) ?: $selected->email }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500">{{ $selected->contact ?: __('No phone number') }}</flux:text>
                </div>

                <div class="flex max-h-[55vh] flex-1 flex-col gap-3 overflow-y-auto p-5">
                    @forelse ($messages as $m)
                        <div wire:key="msg-{{ $m->id }}"
                            class="max-w-[75%] rounded-lg px-3 py-2 text-sm {{ $m->direction === 'outbound' ? 'self-end bg-blue-600 text-white' : 'self-start bg-zinc-100 dark:bg-white/10' }}">
                            <div>{{ $m->body }}</div>
                            <div class="mt-1 text-[11px] opacity-70">
                                {{ $m->direction === 'outbound' ? __('Admin') : __('Salesman') }} ·
                                {{ optional($m->created_at)->format('M d, h:i A') }} ·
                                {{ ucfirst($m->status) }}
                            </div>
                            @if ($m->error_message)
                                <div class="mt-1 text-[11px] text-red-200">{{ $m->error_message }}</div>
                            @endif
                        </div>
                    @empty
                        <flux:text class="text-sm text-zinc-500">{{ __('No messages yet.') }}</flux:text>
                    @endforelse
                </div>

                <form wire:submit="send" class="flex items-end gap-2 border-t border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:textarea wire:model="body" rows="2" class="flex-1" :placeholder="__('Type a message…')" />
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Send') }}</flux:button>
                </form>
            @else
                <div class="flex flex-1 items-center justify-center p-10 text-sm text-zinc-500">
                    {{ __('Select a salesman to view the conversation.') }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
