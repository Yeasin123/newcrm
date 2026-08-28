<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Failed Payment Settings') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Module feature flags. Stored rows overlay config on every request.') }}</flux:text>
        </div>
        <flux:button size="sm" variant="ghost" :href="route('failed-payments.index')" wire:navigate icon="arrow-left">
            {{ __('Back to cases') }}
        </flux:button>
    </div>

    @if ($liveRetryArmed)
        <flux:callout variant="warning" icon="exclamation-triangle"
            :heading="__('Live retry is ARMED — real charges will run against saved CIM profiles.')" />
    @else
        <flux:callout variant="secondary" icon="shield-check"
            :heading="__('Retry is in dry-run / simulation mode. No money moves.')" />
    @endif

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Module') }}</flux:heading>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <flux:switch wire:model="module_enabled" :label="__('Module enabled')" />
                <flux:switch wire:model="capture_enabled" :label="__('Capture failed payments from webhooks')" />
                <flux:switch wire:model="admin_enabled" :label="__('Admin UI enabled')" />
                <flux:switch wire:model="dry_run_mode" :label="__('Dry-run mode (safety)')" />
            </div>
        </flux:card>

        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Retry') }}</flux:heading>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <flux:switch wire:model="automatic_retry_enabled" :label="__('Automatic retry (cron)')" />
                <flux:switch wire:model="manual_retry_enabled" :label="__('Manual retry (admin button)')" />
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="retryable_failure_categories"
                    :label="__('Retryable failure categories')"
                    :description="__('Comma-separated. e.g. declined, insufficient_funds')" />
                <flux:input wire:model="retry_days" :label="__('Retry day offsets')"
                    :description="__('Comma-separated days after first failure. e.g. 1, 3, 7, 14, 30')" />
                <flux:input wire:model="retry_timezone" :label="__('Retry timezone')" />
                <flux:input wire:model="retry_time" :label="__('Retry time of day (HH:MM)')" />
            </div>
        </flux:card>

        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Communications') }}</flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500">
                {{ __('Live SMS / voice / voicemail transports arrive in Phase 9. Preview and dry-run simulation work today.') }}
            </flux:text>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <flux:switch wire:model="communication_simulation_enabled" :label="__('Dry-run simulation enabled')" />
                <flux:switch wire:model="email_enabled" :label="__('Live email')" />
                <flux:switch wire:model="sms_enabled" :label="__('Live SMS')" />
                <flux:switch wire:model="voice_enabled" :label="__('Live voice')" />
                <flux:switch wire:model="voicemail_enabled" :label="__('Live voicemail')" />
            </div>
        </flux:card>

        <div>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save settings') }}</flux:button>
        </div>
    </form>
</div>
