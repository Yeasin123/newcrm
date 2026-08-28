@php
    $statusColor = fn ($s) => match ($s) {
        'sent' => 'green', 'skipped' => 'zinc', 'failed' => 'red', default => 'amber',
    };
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div>
        <flux:heading size="xl">{{ __('Lead Automation') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('AI helper + rule engine status for new-lead automation.') }}</flux:text>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:callout :variant="$welcomeEnabled ? 'success' : 'secondary'" icon="envelope"
            :heading="$welcomeEnabled ? __('Welcome-email automation is ON') : __('Welcome-email automation is OFF (config/ai.php)')" class="flex-1" />
        <flux:callout :variant="$llmEnabled ? 'success' : 'secondary'" icon="sparkles"
            :heading="$llmEnabled ? __('LLM drafting is ON') : __('LLM drafting is OFF — fixed fallback copy is used')" class="flex-1" />
    </div>

    @foreach ($rollup as $actionKey => $byStatus)
        <flux:card class="p-5" wire:key="rollup-{{ $actionKey }}">
            <flux:heading size="sm">{{ str_replace('_', ' ', $actionKey) }}</flux:heading>
            <div class="mt-3 flex flex-wrap gap-3">
                @foreach (['sent', 'skipped', 'failed', 'pending'] as $s)
                    <div class="rounded-lg border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                        <div class="text-xs text-zinc-500">{{ ucfirst($s) }}</div>
                        <div class="text-lg font-semibold">{{ number_format($byStatus[$s] ?? 0) }}</div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endforeach

    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="status" class="max-w-[10rem]">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach (['sent', 'skipped', 'failed', 'pending'] as $s)
                <flux:select.option :value="$s">{{ ucfirst($s) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="action" class="max-w-[13rem]">
            <flux:select.option value="">{{ __('Any action') }}</flux:select.option>
            <flux:select.option value="welcome_email">{{ __('Welcome email') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$logs">
        <flux:table.columns>
            <flux:table.column>{{ __('Lead') }}</flux:table.column>
            <flux:table.column>{{ __('Action') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Detail') }}</flux:table.column>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($logs as $log)
                <flux:table.row :key="'l-'.$log->id">
                    <flux:table.cell variant="strong">
                        @if ($log->lead)
                            <a class="hover:underline" wire:navigate href="{{ route('leads.show', $log->lead) }}">
                                {{ trim($log->lead->first_name.' '.$log->lead->last_name) ?: $log->lead->email ?: __('Lead #').$log->lead_id }}
                            </a>
                        @else
                            {{ __('Lead #').$log->lead_id }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ str_replace('_', ' ', $log->action_key) }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$statusColor($log->status)">{{ ucfirst($log->status) }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate text-xs text-zinc-500">
                        {{ $log->error_message ?: data_get($log->meta, 'skip_reason') ?: data_get($log->meta, 'reason') ?: '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ optional($log->sent_at ?? $log->updated_at)->format('Y-m-d H:i') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($log->status === 'failed')
                            <flux:button size="xs" variant="ghost" wire:click="retry({{ $log->id }})">{{ __('Retry') }}</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="6" class="text-center text-zinc-500">{{ __('No automation runs yet.') }}</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
