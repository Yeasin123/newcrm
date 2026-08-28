<div class="mx-auto max-w-2xl px-4 py-12">
    <h1 class="text-2xl font-bold">{{ __('Payment') }}</h1>

    @if ($this->breakdown)
        <dl class="mt-6 space-y-2 rounded-xl border border-zinc-200 p-5 text-sm dark:border-zinc-800">
            <div class="flex justify-between"><dt>{{ __('Plan') }}</dt><dd>${{ number_format($this->breakdown->packagePrice, 2) }}</dd></div>
            @if ($this->breakdown->addonPrice > 0)
                <div class="flex justify-between"><dt>{{ __('Add-ons') }}</dt><dd>${{ number_format($this->breakdown->addonPrice, 2) }}</dd></div>
            @endif
            @if ($this->breakdown->isDiscounted())
                <div class="flex justify-between text-green-600"><dt>{{ __('Promo') }}</dt><dd>-${{ number_format($this->breakdown->discount, 2) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-bold dark:border-zinc-700">
                <dt>{{ __('Due today') }}</dt><dd>${{ number_format($this->breakdown->payable, 2) }}</dd>
            </div>
            @unless ($this->breakdown->recurring)
                <p class="pt-1 text-xs text-zinc-500">{{ __('One-time prepaid charge — no recurring billing.') }}</p>
            @endunless
        </dl>
    @endif

    @if ($error)
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-500/10 dark:text-red-300">{{ $error }}</div>
    @endif

    <form
        wire:submit="pay"
        class="mt-6 space-y-4"
        x-data="checkoutPayment({
            live: @js($this->liveMode()),
            loginId: @js($this->anetKeys['login_id']),
            clientKey: @js($this->anetKeys['client_key']),
        })"
        x-on:submit.prevent="submit"
    >
        <div>
            <label class="text-sm font-medium">{{ __('Billing address') }}</label>
            <input type="text" wire:model="address" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            @error('address') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <input type="text" wire:model="city" placeholder="{{ __('City') }}" class="rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            <input type="text" wire:model="state" placeholder="{{ __('State') }}" class="rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
        </div>

        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium">{{ __('Card number') }}</label>
                    <input type="text" x-model="cardNumber" inputmode="numeric" autocomplete="cc-number"
                           class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                </div>
                <div>
                    <label class="text-sm font-medium">{{ __('Expiry (MM/YY)') }}</label>
                    <input type="text" wire:model="expiry" placeholder="09/28" autocomplete="cc-exp"
                           class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    @error('expiry') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">{{ __('CVV') }}</label>
                    <input type="text" x-model="cvv" inputmode="numeric" autocomplete="cc-csc"
                           class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                </div>
            </div>
            <p x-show="tokenError" x-text="tokenError" class="mt-2 text-sm text-red-600"></p>
            @unless ($this->liveMode())
                <p class="mt-2 text-xs text-amber-600">{{ __('Billing is in dry-run mode — no real charge is made.') }}</p>
            @endunless
        </div>

        @error('opaqueDataValue') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @error('last4') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <button type="submit" x-bind:disabled="busy"
                class="rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
            <span x-show="!busy">{{ __('Pay now') }}</span>
            <span x-show="busy">{{ __('Processing…') }}</span>
        </button>
        <a href="{{ route('shop.planes') }}" wire:navigate class="ml-3 text-sm text-zinc-500 underline">{{ __('Back') }}</a>
    </form>

    @script
    <script>
        Alpine.data('checkoutPayment', (cfg) => ({
            cardNumber: '', cvv: '', busy: false, tokenError: '',
            _acceptLoaded: false,

            loadAccept() {
                return new Promise((resolve, reject) => {
                    if (window.Accept) return resolve();
                    const s = document.createElement('script');
                    s.src = 'https://js.authorize.net/v1/Accept.js';
                    s.onload = () => resolve();
                    s.onerror = () => reject(new Error('Accept.js failed to load'));
                    document.head.appendChild(s);
                });
            },

            digits(v) { return (v || '').replace(/\D+/g, ''); },

            async submit() {
                this.tokenError = '';
                const pan = this.digits(this.cardNumber);
                if (pan.length < 12) { this.tokenError = 'Please enter a valid card number.'; return; }
                this.busy = true;
                try {
                    if (cfg.live && cfg.loginId && cfg.clientKey) {
                        const [mm, yy] = (this.$wire.expiry || '').replace(/\s/g, '').split('/');
                        const token = await this.tokenise(pan, mm, yy);
                        this.$wire.set('opaqueDataDescriptor', token.dataDescriptor, false);
                        this.$wire.set('opaqueDataValue', token.dataValue, false);
                    } else {
                        // Dry-run: the DryRunPaymentGateway accepts any non-empty token.
                        this.$wire.set('opaqueDataDescriptor', 'COMMON.ACCEPT.INAPP.PAYMENT', false);
                        this.$wire.set('opaqueDataValue', 'DRYRUN-' + Date.now(), false);
                    }
                    this.$wire.set('last4', pan.slice(-4), false);
                    await this.$wire.pay();
                } catch (e) {
                    this.tokenError = e.message || 'Card could not be processed.';
                } finally {
                    this.busy = false;
                }
            },

            async tokenise(pan, mm, yy) {
                await this.loadAccept();
                return new Promise((resolve, reject) => {
                    window.Accept.dispatchData({
                        authData: { clientKey: cfg.clientKey, apiLoginID: cfg.loginId },
                        cardData: { cardNumber: pan, month: mm, year: yy, cardCode: this.digits(this.cvv) },
                    }, (res) => {
                        if (res.messages.resultCode === 'Ok') {
                            resolve({ dataDescriptor: res.opaqueData.dataDescriptor, dataValue: res.opaqueData.dataValue });
                        } else {
                            reject(new Error(res.messages.message[0].text));
                        }
                    });
                });
            },
        }));
    </script>
    @endscript
</div>
