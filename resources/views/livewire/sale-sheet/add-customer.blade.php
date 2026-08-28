@php
    $labelCls = 'block text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-200';
    $inputCls = 'mt-1.5 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-4 py-3 text-sm text-zinc-800 placeholder:text-zinc-400 focus:border-[var(--color-brand-600)] focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-600)]/20 dark:bg-[var(--color-surface-2)] dark:text-zinc-100';
    $cardCls = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-6';
    $usStates = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];
    // Pill states — selected is an unmistakable solid blue in BOTH themes
    // (a navy fill on the navy canvas was invisible in dark mode). Uses
    // stock Tailwind `blue-*` so it never depends on a custom token build.
    $pillBase = 'rounded-full border-2 px-4 py-2 text-sm transition';
    $pillOn = 'border-blue-600 bg-blue-600 font-bold text-white shadow-md';
    $pillOff = 'border-slate-300 font-medium text-slate-700 hover:border-blue-400 hover:text-blue-600 dark:border-white/20 dark:text-zinc-300 dark:hover:border-blue-400';
@endphp

<div class="w-full">
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('customers.index') }}" wire:navigate class="rounded-lg p-2 text-zinc-400 hover:bg-black/5 dark:hover:bg-white/5">
            <flux:icon name="arrow-left" variant="outline" class="size-5" />
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ $fromLeadId ? __('Convert Lead to Customer') : __('Add Customer') }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Create a new home warranty policy') }}</p>
        </div>
    </div>

    @if ($fromLeadId)
        <div class="mb-5 flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            <flux:icon name="user-plus" variant="outline" class="size-4" />
            {{ __('Converting lead :name — the details below were pre-filled from the lead. Review, then submit to create the policy.', ['name' => $fromLeadName]) }}
        </div>
    @endif

    @if ($renewFromClientId)
        <div class="mb-5 flex items-center gap-2 rounded-xl border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300">
            <flux:icon name="arrow-path" variant="outline" class="size-4" />
            {{ __('Renewing :name — the plan below was pre-filled from the current policy. Adjust the term or add-ons, then submit to create the renewal.', ['name' => $renewFromName]) }}
        </div>
    @endif

    @if ($draftRestored)
        <div class="mb-5 flex items-center justify-between rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            <span class="flex items-center gap-2"><flux:icon name="arrow-path" variant="outline" class="size-4" /> {{ __('Draft restored — your progress was saved.') }}</span>
            <button type="button" wire:click="clearDraft" class="font-medium underline">{{ __('Clear & start fresh') }}</button>
        </div>
    @endif

    @if ($error)
        <div class="mb-5 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">{{ $error }}</div>
    @endif

    <form x-data="saleSheetPayment(@js($this->anet))" x-on:submit.prevent="go" class="grid gap-6 lg:grid-cols-[1fr_21rem]">
        <div class="space-y-6">
            {{-- Client Information --}}
            <section class="{{ $cardCls }}">
                <h2 class="text-lg font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Client Information') }}</h2>
                <div class="mt-5 space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="{{ $labelCls }}">{{ __('First Name') }} <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model.blur="client.first_name" placeholder="Jane" class="{{ $inputCls }}">
                            @error('client.first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelCls }}">{{ __('Last Name') }} <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model.blur="client.last_name" placeholder="Smith" class="{{ $inputCls }}">
                            @error('client.last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">{{ __('Email') }} <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <input type="email" wire:model.blur="client.email" placeholder="jane@example.com" class="{{ $inputCls }} pr-11">
                            <flux:icon name="viewfinder-circle" variant="outline" class="pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
                        </div>
                        @error('client.email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">{{ __('Phone') }} <span class="text-rose-500">*</span></label>
                        <input type="tel" wire:model.blur="client.contact" placeholder="888-777-7777" class="{{ $inputCls }}">
                        @error('client.contact') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- Properties --}}
            @foreach ($properties as $i => $property)
                <section class="{{ $cardCls }}" wire:key="prop-{{ $i }}">
                    <div class="-mx-6 -mt-6 mb-6 flex items-center justify-between rounded-t-2xl border-b border-[var(--color-hairline)] bg-[var(--color-surface-2)] px-6 py-4">
                        <span class="flex items-center gap-2 font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">
                            <flux:icon name="building-office-2" variant="outline" class="size-5 text-[var(--color-brand-600)]" />
                            {{ __('Property') }} #{{ $i + 1 }}
                        </span>
                        @if (count($properties) > 1)
                            <button type="button" wire:click="removeProperty({{ $i }})" title="{{ __('Remove property') }}"
                                class="rounded-md p-1 text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10">
                                <flux:icon name="x-mark" variant="outline" class="size-5" />
                            </button>
                        @endif
                    </div>

                    <h3 class="text-base font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Address') }}</h3>
                    <div class="mt-3 space-y-4">
                        <div>
                            <label class="{{ $labelCls }}">{{ __('Street') }} <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model.blur="properties.{{ $i }}.address" placeholder="123 Main St" class="{{ $inputCls }}">
                            @error("properties.$i.address") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="{{ $labelCls }}">{{ __('City') }} <span class="text-rose-500">*</span></label>
                                <input type="text" wire:model.blur="properties.{{ $i }}.city" placeholder="Los Angeles" class="{{ $inputCls }}">
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">{{ __('State') }} <span class="text-rose-500">*</span></label>
                                <select wire:model.blur="properties.{{ $i }}.state" class="{{ $inputCls }}">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach ($usStates as $st) <option value="{{ $st }}">{{ $st }}</option> @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">{{ __('ZIP') }} <span class="text-rose-500">*</span></label>
                                <input type="text" wire:model.blur="properties.{{ $i }}.zip_code" placeholder="90210" class="{{ $inputCls }}">
                            </div>
                        </div>
                        @error("properties.$i.city") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @error("properties.$i.state") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @error("properties.$i.zip_code") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Property Type --}}
                    <h3 class="mt-6 text-base font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Property Type') }}</h3>
                    <div class="mt-3 flex flex-wrap gap-2.5">
                        @foreach ($this->propertyTypes as $type)
                            <button type="button" wire:key="pt-{{ $i }}-{{ $type->id }}"
                                x-on:click="$wire.set('properties.{{ $i }}.property_type', {{ $type->id }})"
                                x-bind:class="pillFor({{ $i }}, 'property_type', {{ $type->id }})"
                                class="{{ $pillBase }}">{{ $type->title }}</button>
                        @endforeach
                    </div>

                    {{-- Property Size --}}
                    <h3 class="mt-6 text-base font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Property Size') }}</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($this->propertySizes as $size)
                            <button type="button" wire:key="ps-{{ $i }}-{{ $size->id }}"
                                x-on:click="$wire.set('properties.{{ $i }}.property_size', {{ $size->id }})"
                                x-bind:class="pillFor({{ $i }}, 'property_size', {{ $size->id }})"
                                class="rounded-full border-2 py-4 text-center text-sm transition">{{ $size->title }}</button>
                        @endforeach
                    </div>
                    @error("properties.$i.property_size") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    {{-- Select Package --}}
                    <h3 class="mt-6 text-base font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Select Package') }}</h3>
                    <select wire:model.live="properties.{{ $i }}.package" class="{{ $inputCls }} mt-3">
                        <option value="">{{ __('Select a plan…') }}</option>
                        @foreach ($this->packages as $package)
                            <option value="{{ $package->id }}">{{ $package->title }}</option>
                        @endforeach
                    </select>
                    @error("properties.$i.package") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    {{-- Payment Structure --}}
                    <h3 class="mt-6 text-base font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Payment Structure') }}</h3>
                    <div class="mt-3 flex flex-wrap gap-2.5">
                        @foreach (config('sale_sheet.terms') as $code => $cfg)
                            <button type="button" wire:key="pterm-{{ $i }}-{{ $code }}"
                                x-on:click="$wire.set('properties.{{ $i }}.payment_term', {{ $code }})"
                                x-bind:class="pillFor({{ $i }}, 'payment_term', {{ $code }})"
                                class="{{ $pillBase }}">{{ __($cfg['label']) }}</button>
                        @endforeach
                    </div>

                    {{-- Services --}}
                    <h3 class="mt-6 text-base font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Services') }}</h3>
                    <div class="mt-3 grid gap-x-6 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($this->addons as $addon)
                            <label @class([
                                'flex items-center gap-2 text-sm',
                                'font-semibold text-[var(--color-brand-600)] dark:text-[var(--color-brand-500)]' => in_array((int) $addon->id, array_map('intval', $property['addons'] ?? []), true),
                                'text-zinc-700 dark:text-zinc-300' => ! in_array((int) $addon->id, array_map('intval', $property['addons'] ?? []), true),
                            ])>
                                <input type="checkbox" value="{{ $addon->id }}" wire:model.live="properties.{{ $i }}.addons"
                                    class="rounded border-zinc-300 text-[var(--color-brand-600)] focus:ring-[var(--color-brand-600)]">
                                <span>{{ $addon->title }}</span>
                            </label>
                        @endforeach
                    </div>

                    {{-- Apply Discount --}}
                    <h3 class="mt-6 text-base font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Apply Discount?') }}</h3>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" value="0" wire:model.live="properties.{{ $i }}.apply_discount" class="text-[var(--color-brand-600)] focus:ring-[var(--color-brand-600)]"> {{ __('No') }}</label>
                        <label class="flex items-center gap-2"><input type="radio" value="1" wire:model.live="properties.{{ $i }}.apply_discount" class="text-[var(--color-brand-600)] focus:ring-[var(--color-brand-600)]"> {{ __('Yes') }}</label>
                        @if (filter_var($property['apply_discount'] ?? false, FILTER_VALIDATE_BOOL))
                            <div class="ml-2 flex flex-wrap gap-2">
                                @foreach (config('sale_sheet.discount_percents') as $pct)
                                    <button type="button" wire:key="disc-{{ $i }}-{{ $pct }}"
                                        x-on:click="$wire.set('properties.{{ $i }}.discount_percent', '{{ $pct }}')"
                                        x-bind:class="pillFor({{ $i }}, 'discount_percent', '{{ $pct }}')"
                                        class="rounded-full border-2 px-3 py-1.5 text-xs transition">{{ $pct }}%</button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach

            <button type="button" wire:click="addProperty"
                class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-[var(--color-navy-900)]/25 bg-[var(--color-surface)] py-4 text-sm font-semibold text-[var(--color-navy-900)] hover:bg-[var(--color-navy-900)]/5 dark:border-white/20 dark:bg-[var(--color-surface)] dark:text-zinc-200 dark:hover:bg-white/5">
                <flux:icon name="plus" variant="micro" class="size-4" /> {{ __('Add Another Property') }}
            </button>

            {{-- No Live Charge --}}
            <div class="flex items-center gap-3 rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] px-5 py-4">
                <button type="button" wire:click="$toggle('noLiveCharge')" role="switch" :aria-checked="@js($noLiveCharge)"
                    @class([
                        'relative h-6 w-11 shrink-0 rounded-full transition',
                        'bg-[var(--color-brand-600)]' => $noLiveCharge,
                        'bg-zinc-300 dark:bg-zinc-600' => ! $noLiveCharge,
                    ])>
                    <span @class(['absolute top-0.5 size-5 rounded-full bg-white transition-all', 'left-[1.375rem]' => $noLiveCharge, 'left-0.5' => ! $noLiveCharge])></span>
                </button>
                <span class="font-medium text-[var(--color-navy-900)] dark:text-zinc-200">{{ __('No Live Charge') }}</span>
                <span class="text-sm text-zinc-500">{{ __('activate the policy without charging') }}</span>
            </div>

            {{-- Payment Information --}}
            @unless ($noLiveCharge)
                <section class="{{ $cardCls }}">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Payment Information') }}</h2>
                        @if ($this->liveMode())
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                {{ __('LIVE') }} &middot; {{ strtoupper($this->anet['environment'] ?? 'sandbox') }}
                            </span>
                        @else
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                {{ __('DRY-RUN — no real transaction') }}
                            </span>
                        @endif
                    </div>

                    <label class="{{ $labelCls }} mt-5">{{ __('Service Call Fee') }} <span class="text-rose-500">*</span></label>
                    <div class="mt-3 flex flex-wrap gap-2.5">
                        @foreach (config('sale_sheet.call_fees') as $fee)
                            <button type="button" wire:key="fee-{{ $fee }}" wire:click="$set('client.call_fee', '{{ $fee }}')"
                                @class([$pillBase, 'px-5', $pillOn => (string) $client['call_fee'] === (string) $fee, $pillOff => (string) $client['call_fee'] !== (string) $fee])>${{ $fee }}</button>
                        @endforeach
                    </div>
                    @error('client.call_fee') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div class="mt-6 overflow-hidden rounded-xl border border-[var(--color-hairline)]">
                        <div class="bg-gradient-to-r from-[var(--color-navy-900)] to-[var(--color-brand-600)] px-5 py-3 font-semibold text-white">
                            {{ __('Payment Method') }}
                        </div>
                        <div class="space-y-4 bg-[var(--color-surface)] p-5 dark:bg-[var(--color-surface-2)]">
                            <div class="flex items-center gap-6 text-sm">
                                <label class="flex items-center gap-2"><input type="radio" value="card" wire:model.live="paymentMethod" class="text-[var(--color-brand-600)] focus:ring-[var(--color-brand-600)]"> {{ __('Card') }}</label>
                                <label class="flex items-center gap-2"><input type="radio" value="bank" wire:model.live="paymentMethod" class="text-[var(--color-brand-600)] focus:ring-[var(--color-brand-600)]"> {{ __('Bank Account') }}</label>
                            </div>

                            @if ($paymentMethod === 'bank')
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input type="text" wire:model.blur="bankRouting" placeholder="{{ __('Routing Number') }}" class="{{ $inputCls }} mt-0">
                                    <input type="text" wire:model.blur="bankAccount" placeholder="{{ __('Account Number') }}" class="{{ $inputCls }} mt-0">
                                </div>
                                <input type="text" wire:model.blur="bankName" placeholder="{{ __('Name on Bank Account') }}" class="{{ $inputCls }} mt-0">
                                <div class="flex flex-wrap gap-2.5">
                                    @foreach (config('sale_sheet.bank_account_types') as $bt)
                                        <button type="button" wire:click="$set('bankType', '{{ $bt }}')"
                                            @class([$pillBase, $pillOn => $bankType === $bt, $pillOff => $bankType !== $bt])>{{ $bt }}</button>
                                    @endforeach
                                </div>
                                @error('bankRouting') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                @error('bankAccount') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                @error('bankName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @else
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input type="text" x-model="cardNumber" x-on:input="cardNumber = fmtCard(cardNumber)"
                                        inputmode="numeric" maxlength="19" placeholder="4111-1111-1111-1111" autocomplete="cc-number"
                                        class="{{ $inputCls }} mt-0 sm:col-span-2">
                                    <input type="text" x-model="cardExpiry" x-on:input="cardExpiry = fmtExpiry(cardExpiry)"
                                        inputmode="numeric" maxlength="5" placeholder="12/36" autocomplete="cc-exp"
                                        class="{{ $inputCls }} mt-0">
                                    <input type="text" x-model="cvv" inputmode="numeric" maxlength="4" placeholder="{{ __('CVV') }}" autocomplete="cc-csc"
                                        class="{{ $inputCls }} mt-0">
                                </div>
                                <p x-show="tokenError" x-text="tokenError" class="text-sm text-red-600"></p>
                                @error('expiry') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                @error('opaqueDataValue') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                @error('cardLast4') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    </div>
                </section>
            @endunless
        </div>

        {{-- Sidebar — one summary card per property, then the grand total --}}
        <aside class="lg:sticky lg:top-20 lg:self-start">
            <div class="space-y-4 rounded-2xl bg-[var(--color-navy-900)] p-4">
                @foreach ($properties as $n => $p)
                    @php
                        $terms = config('sale_sheet.terms');
                        $price = $this->preview->properties[$n] ?? null;
                        $pkg = $this->packages->firstWhere('id', (int) ($p['package'] ?? 0));
                        $tcfg = $terms[(int) ($p['payment_term'] ?? 1)] ?? $terms[1];
                        $pAddons = collect($p['addons'] ?? [])
                            ->map(fn ($id) => $this->addons->firstWhere('id', (int) $id))
                            ->filter()
                            ->map(fn ($a) => ['title' => $a->title, 'price' => (float) $a->{$tcfg['field']} * $tcfg['multiplier']])
                            ->values();
                        $pPct = filled($p['discount_percent'] ?? null) ? (float) $p['discount_percent'] : null;
                    @endphp
                    <div class="rounded-xl bg-white p-5 text-[var(--color-navy-900)]">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-zinc-400">{{ __('Property') }} #{{ $n + 1 }}</p>
                        @if ($pkg && $price)
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold">{{ $pkg->title }}</span>
                                <span class="font-bold">${{ number_format($price->subtotal, 2) }}</span>
                            </div>
                            @if ($price->discount > 0)
                                <div class="mt-1 flex items-center justify-between text-sm font-bold text-red-600">
                                    <span>{{ __('DISCOUNT') }}</span>
                                    <span>-${{ number_format($price->discount, 2) }}@if ($pPct !== null) <span class="text-xs">({{ rtrim(rtrim(number_format($pPct, 2), '0'), '.') }}% {{ __('OFF') }})</span>@endif</span>
                                </div>
                            @endif
                            <div class="mt-2 space-y-1.5 border-t border-zinc-100 pt-2 text-sm">
                                <div class="flex justify-between font-bold"><span>{{ __('SUB TOTAL') }}</span><span>${{ number_format($price->payable, 2) }}</span></div>
                                <div class="flex justify-between font-bold"><span>{{ __('MONTHLY BILLING') }}</span><span>${{ number_format($price->recurring ? $price->payable : 0, 2) }}</span></div>
                            </div>
                            <p class="mt-2 text-sm font-bold">{{ __('START DATE') }}: {{ $this->preview->startDate->format('m/d/Y') }}</p>
                            <p class="mt-2 text-sm font-semibold text-zinc-500">{{ __('Add-ons') }}</p>
                            @forelse ($pAddons as $a)
                                <div class="flex items-start justify-between gap-3 text-sm text-zinc-500">
                                    <span>&bull; {{ $a['title'] }}</span>
                                    <span class="shrink-0 font-semibold text-[var(--color-navy-900)]">+${{ number_format($a['price'], 2) }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-400">&bull; {{ __('No selected') }}</p>
                            @endforelse
                        @else
                            <p class="text-sm text-zinc-400">{{ __('No plan selected yet') }}</p>
                        @endif
                    </div>
                @endforeach

                <div class="rounded-xl bg-white p-5 text-center text-[var(--color-navy-900)]">
                    <p class="text-sm font-bold">{{ __('Total all properties:') }} ${{ number_format($this->preview->totalChargedToday, 2) }}</p>
                    <p class="mt-3 text-sm font-semibold">{{ __('Total being charged today:') }}</p>
                    <p class="text-2xl font-extrabold">${{ number_format($noLiveCharge ? 0 : $this->preview->totalChargedToday, 2) }}</p>
                    <button type="submit" x-bind:disabled="busy"
                        class="mt-4 w-full rounded-lg bg-[var(--color-cta-600)] px-6 py-3 font-bold uppercase tracking-wide text-white hover:bg-[var(--color-cta-500)] disabled:opacity-60">
                        <span x-show="!busy">{{ __('Submit') }}</span>
                        <span x-show="busy">{{ __('Processing…') }}</span>
                    </button>
                </div>
            </div>
        </aside>
    </form>

    @script
    <script>
        Alpine.data('saleSheetPayment', (cfg) => ({
            cardNumber: '', cardExpiry: '', cvv: '', busy: false, tokenError: '',

            // Pill styling, applied client-side so a selection lights up
            // instantly without waiting on a Livewire DOM re-render.
            pillOn: 'border-blue-600 bg-blue-600 font-bold text-white shadow-md',
            pillOff: 'border-slate-300 font-medium text-slate-700 hover:border-blue-400 hover:text-blue-600 dark:border-white/25 dark:text-zinc-300',
            pillFor(i, key, val) {
                const cur = this.$wire.properties?.[i]?.[key];
                return String(cur) === String(val) ? this.pillOn : this.pillOff;
            },

            digits(v) { return (v || '').replace(/\D/g, ''); },
            fmtCard(v) { return this.digits(v).slice(0, 16).replace(/(\d{4})(?=\d)/g, '$1-'); },
            fmtExpiry(v) {
                const d = this.digits(v).slice(0, 4);
                return d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
            },

            loadAcceptJs() {
                return new Promise((resolve, reject) => {
                    if (window.Accept) return resolve();
                    const s = document.createElement('script');
                    s.src = cfg.accept_js_url;
                    s.onload = () => resolve();
                    s.onerror = () => reject(new Error('Could not load Accept.js from Authorize.Net.'));
                    document.head.appendChild(s);
                });
            },

            async tokenise(pan) {
                await this.loadAcceptJs();
                const [mm, yy] = (this.cardExpiry || '').split('/');
                return new Promise((resolve, reject) => {
                    window.Accept.dispatchData({
                        authData: { clientKey: cfg.client_key, apiLoginID: cfg.login_id },
                        cardData: { cardNumber: pan, month: mm, year: yy, cardCode: this.digits(this.cvv) },
                    }, (res) => {
                        if (res.messages.resultCode === 'Ok') {
                            resolve({ d: res.opaqueData.dataDescriptor, v: res.opaqueData.dataValue });
                        } else {
                            reject(new Error(res.messages.message[0].text));
                        }
                    });
                });
            },

            async go() {
                this.tokenError = '';
                const useCard = ! this.$wire.noLiveCharge && this.$wire.paymentMethod === 'card';

                if (useCard) {
                    const pan = this.digits(this.cardNumber);
                    if (pan.length < 15) { this.tokenError = 'Please enter a valid card number.'; return; }
                    if (! /^\d{2}\/\d{2}$/.test(this.cardExpiry)) { this.tokenError = 'Enter the expiry as MM/YY.'; return; }

                    this.$wire.set('expiry', this.cardExpiry, false);
                    this.$wire.set('cardLast4', pan.slice(-4), false);

                    if (cfg.live && cfg.login_id && cfg.client_key) {
                        this.busy = true;
                        try {
                            const tok = await this.tokenise(pan);
                            this.$wire.set('opaqueDataDescriptor', tok.d, false);
                            this.$wire.set('opaqueDataValue', tok.v, false);
                        } catch (e) {
                            this.busy = false;
                            this.tokenError = e.message || 'The card could not be tokenised.';
                            return;
                        }
                    } else {
                        // Dry-run: DryRunPaymentGateway accepts any non-empty token.
                        this.$wire.set('opaqueDataDescriptor', 'COMMON.ACCEPT.INAPP.PAYMENT', false);
                        this.$wire.set('opaqueDataValue', 'DRYRUN-' + Date.now(), false);
                    }
                }

                this.busy = true;
                try { await this.$wire.submit(); } finally { this.busy = false; }
            },
        }));
    </script>
    @endscript
</div>
