@php($cs = \App\Enums\ClaimStatus::tryFrom((int) $claim->status))
<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <flux:button icon="arrow-left" variant="ghost" size="sm" :href="route('claims.index')" wire:navigate />
            <div>
                <flux:heading size="xl">{{ __('Claim') }} {{ $claim->claim_no ?: $claim->id }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500">
                    {{ $claim->client ? trim($claim->client->first_name.' '.$claim->client->last_name) : '—' }}
                    &middot; #{{ $claim->client->policy_number ?? '—' }}
                    &middot; {{ __('Opened') }} {{ optional($claim->created_at)->format('M j, Y') ?? '—' }}
                </flux:text>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge :color="$cs?->color() ?? 'zinc'">{{ $cs?->label() ?? $claim->status }}</flux:badge>
            @if ($claim->is_callfee)<flux:badge color="amber">{{ __('Call fee') }}</flux:badge>@endif
            <flux:button size="sm" variant="ghost" wire:click="toggleCallFee">
                {{ $claim->is_callfee ? __('Clear call fee') : __('Mark call fee') }}
            </flux:button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Problem') }}</flux:heading>
            <dl class="space-y-2 text-sm">
                <x-customer-field :label="__('System')" :value="$claim->systemRef->title ?? null" />
                <x-customer-field :label="__('Brand')" :value="$claim->brandRef->title ?? null" />
                <x-customer-field :label="__('Issue')" :value="$claim->other_issue ?: $claim->issue" />
                <x-customer-field :label="__('Serial #')" :value="$claim->serial_no" />
                <x-customer-field :label="__('Model #')" :value="$claim->model_no" />
                <x-customer-field :label="__('Location')" :value="$claim->location" />
            </dl>
            @if ($claim->problem)
                <flux:text class="whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-300">{{ $claim->problem }}</flux:text>
            @endif
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Dispatch') }}</flux:heading>
            <form wire:submit="assignTechnician" class="space-y-2">
                <flux:select wire:model="techId" :label="__('Technician')">
                    <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                    @foreach ($technicians as $t)
                        <flux:select.option value="{{ $t->id }}" wire:key="t-{{ $t->id }}">{{ $t->full_name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="techSchedule" :label="__('Schedule')" :placeholder="__('e.g. Tue 9–12')" />
                <flux:button type="submit" size="sm" variant="primary" class="w-full">{{ __('Save dispatch') }}</flux:button>
            </form>
            <form wire:submit="changeStatus" class="flex items-end gap-2">
                <flux:select wire:model="statusValue" :label="__('Status')" class="flex-1">
                    @foreach ($statuses as $s)
                        <flux:select.option value="{{ $s->value }}" wire:key="s-{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" size="sm">{{ __('Set') }}</flux:button>
            </form>
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Money') }}</flux:heading>
            <dl class="space-y-2 text-sm">
                <x-customer-field :label="__('Owed price')" :value="'$'.number_format((float) $claim->owed_price, 2)" />
                <x-customer-field :label="__('Total price')" :value="'$'.number_format((float) $claim->total_price, 2)" />
                <x-customer-field :label="__('Tech paid')" :value="$claim->tech_paid_status ? __('Yes') : __('No')" />
                <x-customer-field :label="__('Replit status')" :value="$claim->replit_status" />
            </dl>
        </flux:card>
    </div>

    {{-- Parts --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Parts') }}</flux:heading>
        <form wire:submit="addPart" class="grid gap-2 sm:grid-cols-4">
            <flux:input wire:model="partName" :placeholder="__('Part name')" />
            <flux:input wire:model="partPrice" type="number" step="0.01" :placeholder="__('Price')" />
            <flux:input wire:model="partOrderNumber" :placeholder="__('Order #')" />
            <flux:button type="submit" variant="primary">{{ __('Add part') }}</flux:button>
        </form>
        @error('partName') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Part') }}</flux:table.column>
                <flux:table.column>{{ __('Price') }}</flux:table.column>
                <flux:table.column>{{ __('Order #') }}</flux:table.column>
                <flux:table.column>{{ __('Bought by') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($claim->parts as $part)
                    <flux:table.row :key="'p-'.$part->id">
                        <flux:table.cell variant="strong">{{ $part->name }}</flux:table.cell>
                        <flux:table.cell>${{ number_format((float) $part->price, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $part->order_number ?: '—' }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ (int) $part->who_buy === 1 ? __('AHC') : __('Technician') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="4" class="text-center text-zinc-500">{{ __('No parts.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Close claim --}}
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Close claim') }}</flux:heading>
            @if ($claim->closedDetails->isNotEmpty())
                <flux:callout variant="success" icon="check-circle">
                    {{ __('Closed :when — spent $:amt', [
                        'when' => optional($claim->closedDetails->first()->created_at)->format('M j, Y'),
                        'amt' => number_format((float) $claim->closedDetails->first()->claim_spent_price, 2),
                    ]) }}
                </flux:callout>
            @endif
            <form wire:submit="closeClaim" class="space-y-2">
                <flux:input wire:model="closeDispatch" type="number" step="0.01" :label="__('Dispatch amount')" />
                <flux:input wire:model="closeSpent" type="number" step="0.01" :label="__('Total spent')" />
                <flux:textarea wire:model="closeNotes" rows="2" :label="__('Closing notes')" />
                <flux:button type="submit" variant="primary" class="w-full">{{ __('Record & close') }}</flux:button>
            </form>
        </flux:card>

        {{-- Timeline --}}
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Timeline') }}</flux:heading>
            <form wire:submit="addNote" class="flex items-start gap-2">
                <flux:textarea wire:model="noteBody" rows="2" class="flex-1" :placeholder="__('Add a note…')" />
                <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
            </form>
            @error('noteBody') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-700">
                @forelse ($claim->tracks as $t)
                    <li class="py-2" wire:key="tr-{{ $t->id }}">
                        <div class="whitespace-pre-line">{{ $t->comments ?: $t->button_text }}</div>
                        <div class="text-xs text-zinc-500">{{ $t->author?->name ?? $t->who_view ?? __('System') }} &middot; {{ $t->create_date ?: optional($t->created_at)->diffForHumans() }}</div>
                    </li>
                @empty
                    <li class="py-2 text-zinc-500">{{ __('No activity yet.') }}</li>
                @endforelse
            </ul>
        </flux:card>
    </div>
</div>
