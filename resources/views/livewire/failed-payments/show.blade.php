@php
    $statusColor = fn (string $s) => match ($s) {
        'recovered', 'resolved', 'completed', 'success' => 'green',
        'cancelled', 'skipped' => 'zinc',
        'unmatched', 'paused', 'pending', 'simulated' => 'amber',
        default => 'red',
    };
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <flux:button size="xs" variant="ghost" :href="route('failed-payments.index')" wire:navigate icon="arrow-left" />
                <flux:heading size="xl">
                    {{ trim(($case->client?->first_name ?? '').' '.($case->client?->last_name ?? '')) ?: ($case->client?->email ?? __('Client #').$case->client_id) }}
                </flux:heading>
                <flux:badge :color="$statusColor($case->current_status)">
                    {{ ucfirst(str_replace('_', ' ', $case->current_status)) }}
                </flux:badge>
                <flux:badge size="sm" color="zinc">{{ $case->priority }}</flux:badge>
            </div>
            <flux:text class="mt-1 text-zinc-500">
                {{ $case->case_uuid }}
                @if ($case->client)
                    &middot; <a class="hover:underline" wire:navigate href="{{ route('customers.show', $case->client) }}">{{ __('open customer') }}</a>
                @endif
            </flux:text>
        </div>

        @if ($canUpdate)
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" wire:click="transitionCase('manually_resolved')">{{ __('Mark resolved') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="transitionCase('ignored')">{{ __('Ignore') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="transitionCase('reopen')">{{ __('Reopen') }}</flux:button>
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- summary --}}
        <flux:card class="p-5 lg:col-span-2">
            <flux:heading size="sm">{{ __('Case') }}</flux:heading>
            <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                <div><dt class="text-zinc-500">{{ __('Amount') }}</dt><dd>${{ number_format((float) $case->failed_amount, 2) }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Frequency') }}</dt><dd>{{ $case->payment_frequency ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Category') }}</dt><dd>{{ $case->failure_category ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Failure code') }}</dt><dd>{{ $case->failure_code ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Source') }}</dt><dd>{{ $case->failure_source ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Attempts') }}</dt><dd>{{ $case->attempt_count }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('First failed') }}</dt><dd>{{ optional($case->first_failed_at)->format('Y-m-d H:i') ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Latest failed') }}</dt><dd>{{ optional($case->latest_failed_at)->format('Y-m-d H:i') ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Recovered') }}</dt><dd>{{ optional($case->recovered_at)->format('Y-m-d H:i') ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Subscription') }}</dt><dd class="truncate">{{ $case->arb_subscription_id ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Transaction') }}</dt><dd class="truncate">{{ $case->anet_transaction_id ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Merchant') }}</dt><dd>{{ $case->authorizeAccount?->name ?: '—' }}</dd></div>
            </dl>
            @if ($case->failure_reason)
                <flux:text class="mt-3 text-sm text-zinc-500">{{ $case->failure_reason }}</flux:text>
            @endif
        </flux:card>

        {{-- assignment --}}
        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Assignment') }}</flux:heading>
            <div class="mt-3 flex flex-col gap-3">
                <flux:select wire:model="assigned_user_id" :disabled="! $canAssign">
                    <flux:select.option :value="null">{{ __('Unassigned') }}</flux:select.option>
                    @foreach ($assignmentUsers as $u)
                        <flux:select.option :value="$u->id">{{ trim($u->first_name.' '.$u->last_name) ?: $u->email }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($canAssign)
                    <flux:button size="sm" wire:click="saveAssignment">{{ __('Save assignment') }}</flux:button>
                @endif
            </div>
        </flux:card>
    </div>

    {{-- retry schedules --}}
    <flux:card class="p-5">
        <flux:heading size="sm">{{ __('Retry schedules') }}</flux:heading>
        @if ($lastSimResult)
            <flux:callout variant="secondary" class="mt-2" icon="beaker"
                :heading="__('Last simulation on #:id — :status (:result)', ['id' => $lastSimResult['schedule_id'], 'status' => $lastSimResult['status'], 'result' => $lastSimResult['attempt_result']])" />
        @endif
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>#</flux:table.column>
                <flux:table.column>{{ __('Scheduled') }}</flux:table.column>
                <flux:table.column>{{ __('Delay') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Last result') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($retrySchedules as $s)
                    <flux:table.row :key="'rs-'.$s->id">
                        <flux:table.cell variant="strong">{{ $s->sequence_number }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ optional($s->scheduled_at)->format('Y-m-d H:i') ?: '—' }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $s->retry_delay_days }}d</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$statusColor($s->status)">{{ ucfirst($s->status) }}</flux:badge></flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ data_get($s->metadata, 'last_attempt_result', '—') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-1">
                                @if ($canRetryControl && $s->status === 'pending')
                                    <flux:button size="xs" variant="ghost" wire:click="controlRetry({{ $s->id }}, 'pause')">{{ __('Pause') }}</flux:button>
                                @endif
                                @if ($canRetryControl && $s->status === 'paused')
                                    <flux:button size="xs" variant="ghost" wire:click="controlRetry({{ $s->id }}, 'resume')">{{ __('Resume') }}</flux:button>
                                @endif
                                @if ($canRetryControl && in_array($s->status, ['pending', 'paused'], true))
                                    <flux:button size="xs" variant="ghost" wire:click="controlRetry({{ $s->id }}, 'cancel')">{{ __('Cancel') }}</flux:button>
                                @endif
                                @if ($canSimulateRetry && in_array($s->status, ['pending', 'paused'], true))
                                    <flux:button size="xs" wire:click="simulateRetry({{ $s->id }})">{{ __('Simulate') }}</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="6" class="text-center text-zinc-500">{{ __('No retry schedules.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- communications --}}
    <flux:card class="p-5">
        <flux:heading size="sm">{{ __('Communications') }}</flux:heading>
        @if ($canCommunicate)
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <flux:select wire:model.live="commChannel" class="max-w-[9rem]" :label="__('Channel')">
                    @foreach ($channels as $ch)
                        <flux:select.option :value="$ch">{{ ucfirst($ch) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="commTemplate" class="max-w-[16rem]" :label="__('Template')">
                    @foreach (($templatesByChannel[$commChannel] ?? []) as $opt)
                        <flux:select.option :value="$opt['id'] ?? $opt['value'] ?? ''">{{ $opt['label'] ?? ($opt['id'] ?? '') }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="commRetryStage" class="max-w-[9rem]" :label="__('Retry stage')" />
                <flux:button size="sm" variant="ghost" wire:click="previewCommunication">{{ __('Preview') }}</flux:button>
                <flux:button size="sm" wire:click="simulateCommunication">{{ __('Dry-run') }}</flux:button>
                <flux:button size="sm" variant="danger" wire:click="sendCommunication"
                    wire:confirm="{{ __('Send a LIVE communication to the customer?') }}">{{ __('Send live') }}</flux:button>
            </div>

            @if ($commPreview)
                <flux:callout variant="secondary" class="mt-3">
                    <div class="text-xs text-zinc-500">{{ __('To') }}: {{ $commPreview['masked_recipient'] ?: '—' }} &middot; {{ $commPreview['channel'] }}</div>
                    @if ($commPreview['subject'])
                        <div class="mt-1 font-medium">{{ $commPreview['subject'] }}</div>
                    @endif
                    <pre class="mt-2 whitespace-pre-wrap text-sm">{{ $commPreview['message'] }}</pre>
                </flux:callout>
            @endif
        @endif

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Channel') }}</flux:table.column>
                <flux:table.column>{{ __('Template') }}</flux:table.column>
                <flux:table.column>{{ __('Recipient') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($communications as $m)
                    <flux:table.row :key="'cm-'.$m->id">
                        <flux:table.cell class="text-zinc-500">{{ optional($m->created_at)->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ ucfirst($m->channel) }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $m->template_name ?: '—' }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $m->masked_recipient ?? '—' }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$statusColor($m->status)">{{ ucfirst($m->status) }}</flux:badge></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No communications yet.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- notes --}}
    <flux:card class="p-5">
        <flux:heading size="sm">{{ __('Notes') }}</flux:heading>
        @if ($canNotes)
            <div class="mt-3 flex flex-col gap-2">
                <flux:textarea wire:model="noteBody" rows="2" :placeholder="__('Add an internal note…')" />
                <div class="flex items-center gap-2">
                    <flux:input wire:model="noteType" class="max-w-[10rem]" :placeholder="__('note type')" />
                    <flux:button size="sm" wire:click="addNote">{{ $editingNoteId ? __('Update note') : __('Add note') }}</flux:button>
                    @if ($editingNoteId)
                        <flux:button size="sm" variant="ghost" wire:click="cancelNoteEdit">{{ __('Cancel') }}</flux:button>
                    @endif
                </div>
            </div>
        @endif
        <div class="mt-4 flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($notes as $n)
                <div class="flex items-start justify-between gap-3 py-2" wire:key="nt-{{ $n->id }}">
                    <div>
                        <div class="text-xs text-zinc-500">{{ $n->note_type }} &middot; {{ optional($n->created_at)->format('Y-m-d H:i') }}</div>
                        <div class="text-sm">{{ $n->note_body }}</div>
                    </div>
                    @if ($canNotes)
                        <div class="flex gap-1">
                            <flux:button size="xs" variant="ghost" wire:click="editNote({{ $n->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="xs" variant="ghost" wire:click="deleteNote({{ $n->id }})"
                                wire:confirm="{{ __('Delete this note?') }}">{{ __('Delete') }}</flux:button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="py-2 text-sm text-zinc-500">{{ __('No notes.') }}</div>
            @endforelse
        </div>
    </flux:card>

    {{-- history --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Attempts') }}</flux:heading>
            <div class="mt-3 flex flex-col divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                @forelse ($attempts as $a)
                    <div class="flex items-center justify-between py-2" wire:key="at-{{ $a->id }}">
                        <span>#{{ $a->attempt_number }} · {{ $a->attempt_type }}</span>
                        <span class="text-zinc-500">{{ $a->status }} · {{ optional($a->completed_at)->format('m-d H:i') }}</span>
                    </div>
                @empty
                    <div class="py-2 text-zinc-500">{{ __('No attempts.') }}</div>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Status history') }}</flux:heading>
            <div class="mt-3 flex flex-col divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                @forelse ($statusHistories as $h)
                    <div class="py-2" wire:key="sh-{{ $h->id }}">
                        <div>{{ $h->previous_status ?: '—' }} → <strong>{{ $h->new_status }}</strong></div>
                        <div class="text-xs text-zinc-500">{{ $h->change_reason }} · {{ optional($h->created_at)->format('Y-m-d H:i') }}</div>
                    </div>
                @empty
                    <div class="py-2 text-zinc-500">{{ __('No status changes.') }}</div>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Webhook events') }}</flux:heading>
            <div class="mt-3 flex flex-col divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                @forelse ($webhookEvents as $e)
                    <div class="flex items-center justify-between py-2" wire:key="wh-{{ $e->id }}">
                        <span>{{ $e->event_type ?: 'event' }}</span>
                        <span class="text-zinc-500">{{ $e->signature_validation_status }} · {{ optional($e->received_at)->format('m-d H:i') }}</span>
                    </div>
                @empty
                    <div class="py-2 text-zinc-500">{{ __('No webhook events linked.') }}</div>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Audit log') }}</flux:heading>
            <div class="mt-3 flex max-h-72 flex-col divide-y divide-zinc-200 overflow-y-auto text-sm dark:divide-zinc-700">
                @forelse ($auditLogs as $l)
                    <div class="py-2" wire:key="al-{{ $l->id }}">
                        <div>{{ $l->action }}</div>
                        <div class="text-xs text-zinc-500">{{ $l->notes }} · {{ optional($l->created_at)->format('Y-m-d H:i') }}</div>
                    </div>
                @empty
                    <div class="py-2 text-zinc-500">{{ __('No audit entries.') }}</div>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>
