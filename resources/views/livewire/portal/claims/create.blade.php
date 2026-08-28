<div class="mx-auto max-w-2xl px-4 py-12">
    <a href="{{ route('portal.claims.index') }}" wire:navigate class="text-sm text-zinc-500 underline">&larr; {{ __('All claims') }}</a>
    <h1 class="mt-2 text-2xl font-bold">{{ __('File a claim') }}</h1>

    @if ($gateError)
        <div class="mt-6 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ $gateError }}</div>
    @else
        @if ($error)
            <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-500/10 dark:text-red-300">{{ $error }}</div>
        @endif

        <form wire:submit="file" class="mt-6 space-y-4"
              x-data="claimPayment()" x-on:submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-medium">{{ __('System') }}</label>
                    <select wire:model.live="system" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="">{{ __('Select…') }}</option>
                        @foreach ($systems as $s) <option value="{{ $s->id }}">{{ $s->title }}</option> @endforeach
                    </select>
                    @error('system') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">{{ __('Issue') }}</label>
                    <select wire:model="issue" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="">{{ __('Select…') }}</option>
                        @foreach ($issues as $i) <option value="{{ $i->id }}">{{ $i->title }}</option> @endforeach
                    </select>
                    @error('issue') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">{{ __('Brand') }}</label>
                    <select wire:model="brand" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="">{{ __('Select…') }}</option>
                        @foreach ($brands as $b) <option value="{{ $b->id }}">{{ $b->title }}</option> @endforeach
                    </select>
                    @error('brand') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">{{ __('Model # (optional)') }}</label>
                    <input type="text" wire:model="model_no" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                </div>
            </div>

            <div>
                <label class="text-sm font-medium">{{ __('Describe the problem') }}</label>
                <textarea wire:model="problem" rows="3" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900"></textarea>
                @error('problem') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">{{ __('When did it last work properly?') }}</label>
                <input type="text" wire:model="last_time_working_detail" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                @error('last_time_working_detail') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <p class="text-sm font-medium">{{ __('Service-call fee: $:fee', ['fee' => number_format($callFee, 2)]) }}</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">{{ __('Card number') }}</label>
                        <input type="text" x-model="cardNumber" inputmode="numeric" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    </div>
                    <div>
                        <label class="text-sm font-medium">{{ __('Expiry (MM/YY)') }}</label>
                        <input type="text" wire:model="expiry" placeholder="09/28" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                        @error('expiry') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium">{{ __('CVV') }}</label>
                        <input type="text" x-model="cvv" inputmode="numeric" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    </div>
                </div>
                <p x-show="tokenError" x-text="tokenError" class="mt-2 text-sm text-red-600"></p>
                @error('opaqueDataValue') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @error('last4') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" x-bind:disabled="busy"
                    class="rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                <span x-show="!busy">{{ __('Submit claim & pay fee') }}</span>
                <span x-show="busy">{{ __('Processing…') }}</span>
            </button>
        </form>

        @script
        <script>
            Alpine.data('claimPayment', () => ({
                cardNumber: '', cvv: '', busy: false, tokenError: '',
                async submit() {
                    this.tokenError = '';
                    const pan = (this.cardNumber || '').replace(/\D+/g, '');
                    if (pan.length < 12) { this.tokenError = 'Please enter a valid card number.'; return; }
                    this.busy = true;
                    try {
                        this.$wire.set('opaqueDataDescriptor', 'COMMON.ACCEPT.INAPP.PAYMENT', false);
                        this.$wire.set('opaqueDataValue', 'DRYRUN-' + Date.now(), false);
                        this.$wire.set('last4', pan.slice(-4), false);
                        await this.$wire.file();
                    } catch (e) {
                        this.tokenError = e.message || 'Card could not be processed.';
                    } finally {
                        this.busy = false;
                    }
                },
            }));
        </script>
        @endscript
    @endif
</div>
