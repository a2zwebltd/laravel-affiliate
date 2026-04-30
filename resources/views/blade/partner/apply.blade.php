@extends(config('affiliate.layout'))

@section('title', __('Apply to Affiliate Program'))

@section('content')
@php
    $partner = $user->affiliatePartner;
    // A stub partner row may exist (just a share-code, applied_at IS NULL).
    // "Edit mode" only when user actually applied before.
    $isEdit = $partner !== null && $partner->applied_at !== null;
    $action = $isEdit
        ? route(config('affiliate.routes.name_prefix', 'affiliate.').'payout-details.update')
        : route(config('affiliate.routes.name_prefix', 'affiliate.').'apply.store');
@endphp
<div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ $isEdit ? __('Update payout details') : __('Apply to the Affiliate Program') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Provide payout details.') }} {{ $isEdit ? '' : __('You must accept both Terms documents below.') }}</p>

    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="mt-8 space-y-6" x-data="{
        method: '{{ old('payout_method', $partner->payout_method ?? 'paypal') }}',
        isCompany: {{ old('is_company', $partner->is_company ?? false) ? 'true' : 'false' }}
    }">
        @csrf
        @if ($isEdit) @method('PATCH') @endif

        @unless ($isEdit)
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Your details') }}</h2>
                <p class="mt-1 text-xs text-zinc-500">{{ __('These appear on your commission statements. Required.') }}</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Legal / business name') }}</label>
                        <input type="text" name="billing_full_name" required value="{{ old('billing_full_name', $partner->billing_full_name ?? ($defaults['full_name'] ?? $user->name)) }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Address') }}</label>
                        <textarea name="billing_address" required rows="2" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" placeholder="{{ __('Street, city, postal code, country') }}">{{ old('billing_address', $partner->billing_address ?? ($defaults['address'] ?? '')) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Country of tax residence') }}</label>
                        <div x-data="{
                                open: false,
                                search: '',
                                selected: @js(strtoupper((string) old('country_of_tax_residence', $partner->country_of_tax_residence ?? ($defaults['country_of_tax_residence'] ?? '')))),
                                countries: @js($countries),
                                get filtered() {
                                    const q = this.search.toLowerCase().trim();
                                    const entries = Object.entries(this.countries);
                                    if (! q) return entries;
                                    return entries.filter(([code, name]) =>
                                        name.toLowerCase().includes(q) || code.toLowerCase().includes(q)
                                    );
                                },
                                label() { return this.selected ? (this.countries[this.selected] ?? this.selected) : ''; },
                                pick(code) { this.selected = code; this.search = ''; this.open = false; }
                             }"
                             @click.outside="open = false"
                             @keydown.escape.window="open = false"
                             class="relative mt-1">
                            <input type="hidden" name="country_of_tax_residence" :value="selected" required>
                            <button type="button"
                                    @click="open = ! open; if (open) $nextTick(() => $refs.search.focus())"
                                    class="flex w-full items-center justify-between rounded-lg border border-zinc-300 bg-white px-3 py-2 text-left text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                <span x-text="label() || '{{ __('Select country') }}'" :class="{ 'text-zinc-400': ! selected }"></span>
                                <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-transition.opacity x-cloak
                                 class="absolute z-20 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                <input type="text" x-model="search" x-ref="search" placeholder="{{ __('Search country or code…') }}"
                                       class="block w-full rounded-t-lg border-0 border-b border-zinc-200 px-3 py-2 text-sm focus:ring-0 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                <ul class="max-h-60 overflow-y-auto py-1">
                                    <template x-for="[code, name] in filtered" :key="code">
                                        <li @click="pick(code)"
                                            class="flex cursor-pointer items-center justify-between px-3 py-1.5 text-sm hover:bg-emerald-50 dark:text-zinc-100 dark:hover:bg-emerald-950/30">
                                            <span x-text="name"></span>
                                            <span class="font-mono text-xs text-zinc-400" x-text="code"></span>
                                        </li>
                                    </template>
                                    <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-zinc-400">{{ __('No matches') }}</li>
                                </ul>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-zinc-500">{{ __('Used to determine VAT/withholding requirements.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Contact email') }} <span class="text-zinc-400 font-normal">{{ __('(optional)') }}</span></label>
                        <input type="email" name="contact_email" value="{{ old('contact_email', $partner->contact_email ?? ($defaults['email'] ?? '')) }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Contact phone') }} <span class="text-zinc-400 font-normal">{{ __('(optional)') }}</span></label>
                        <input type="tel" name="contact_phone" value="{{ old('contact_phone', $partner->contact_phone ?? ($defaults['phone'] ?? '')) }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                    </div>
                </div>
            </div>
        @endunless

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Payout method') }}</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 p-4 transition" :class="method === 'paypal' ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30' : 'border-zinc-200 dark:border-zinc-800'">
                    <input type="radio" name="payout_method" value="paypal" x-model="method" class="mt-1">
                    <div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('PayPal') }}</div>
                        <div class="mt-0.5 text-xs text-zinc-500">{{ __('Fastest, no fees on our side.') }}</div>
                    </div>
                </label>
                <label class="flex cursor-not-allowed items-start gap-3 rounded-xl border-2 border-zinc-200 p-4 opacity-60 transition dark:border-zinc-800" aria-disabled="true" title="{{ __('Coming soon') }}">
                    <input type="radio" name="payout_method" value="bank_transfer" disabled class="mt-1">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Bank transfer (IBAN)') }}</span>
                            <span class="rounded-full bg-zinc-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ __('Coming soon') }}</span>
                        </div>
                        <div class="mt-0.5 text-xs text-zinc-500">{{ __('For EU/SEPA partners.') }}</div>
                    </div>
                </label>
            </div>

            <div x-show="method === 'paypal'" x-transition class="mt-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('PayPal email') }}</label>
                    <input type="email" name="paypal_email" value="{{ old('paypal_email', $partner->paypal_email ?? '') }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" placeholder="you@paypal.com">
                </div>
            </div>

            <div x-show="method === 'bank_transfer'" x-transition class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Account holder') }}</label>
                    <input type="text" name="bank_account_holder" value="{{ old('bank_account_holder', $partner->bank_account_holder ?? '') }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('IBAN') }}</label>
                    <input type="text" name="bank_iban" value="{{ old('bank_iban', $partner->bank_iban ?? '') }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm font-mono dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('SWIFT/BIC') }} <span class="text-zinc-400 font-normal">{{ __('(optional)') }}</span></label>
                    <input type="text" name="bank_swift" value="{{ old('bank_swift', $partner->bank_swift ?? '') }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm font-mono dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Bank address') }} <span class="text-zinc-400 font-normal">{{ __('(optional)') }}</span></label>
                    <input type="text" name="bank_address" value="{{ old('bank_address', $partner->bank_address ?? '') }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Company invoicing') }} <span class="text-zinc-400 text-xs font-normal">{{ __('(optional)') }}</span></h2>
            <label class="mt-4 flex items-center gap-2">
                <input type="checkbox" name="is_company" value="1" x-model="isCompany" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('I am invoicing as a company') }}</span>
            </label>
            <div x-show="isCompany" x-transition class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Company name') }}</label>
                    <input type="text" name="company_name" value="{{ old('company_name', $partner->company_name ?? '') }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Tax ID / VAT') }} <span class="text-zinc-400 font-normal">{{ __('(optional)') }}</span></label>
                    <input type="text" name="tax_id" value="{{ old('tax_id', $partner->tax_id ?? '') }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm font-mono dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                </div>
            </div>
        </div>

        @if (! $isEdit)
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Terms & Conditions') }}</h2>
                <div class="mt-4 space-y-3">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="accept_general_terms" value="1" required class="mt-1 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">
                            {!! __('I have read and accept the :link.', ['link' => '<a href="'.e($general_terms_url).'" target="_blank" class="font-semibold text-emerald-700 underline hover:no-underline dark:text-emerald-400">'.e(__('general Terms of Service')).'</a>']) !!}
                        </span>
                    </label>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <a href="{{ route(config('affiliate.routes.name_prefix', 'affiliate.').'dashboard') }}" class="text-sm font-semibold text-zinc-600 hover:underline">{{ __('← Back') }}</a>
            <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                {{ $isEdit ? __('Save details') : __('Submit application') }}
            </button>
        </div>
    </form>
</div>
@endsection
