<div>
    <form wire:submit="submit" class="space-y-6">

        @error('eligibility') <div class="rounded bg-rose-50 p-3 text-sm text-rose-800">{{ $message }}</div> @enderror

        <div>
            <label class="block text-sm font-medium">{{ __('Payout method') }}</label>
            <select wire:model.live="payout_method" class="mt-1 block w-full rounded border-zinc-300">
                <option value="paypal">{{ __('PayPal') }}</option>
                <option value="bank_transfer">{{ __('Bank transfer (IBAN)') }}</option>
            </select>
        </div>

        @if ($payout_method === 'paypal')
            <div>
                <label class="block text-sm font-medium">{{ __('PayPal email') }}</label>
                <input type="email" wire:model="paypal_email" class="mt-1 block w-full rounded border-zinc-300">
                @error('paypal_email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium">{{ __('Account holder') }}</label>
                    <input type="text" wire:model="bank_account_holder" class="mt-1 block w-full rounded border-zinc-300">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium">{{ __('IBAN') }}</label>
                    <input type="text" wire:model="bank_iban" class="mt-1 block w-full rounded border-zinc-300 font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium">{{ __('SWIFT/BIC') }}</label>
                    <input type="text" wire:model="bank_swift" class="mt-1 block w-full rounded border-zinc-300 font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium">{{ __('Bank address') }}</label>
                    <input type="text" wire:model="bank_address" class="mt-1 block w-full rounded border-zinc-300">
                </div>
            </div>
        @endif

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model.live="is_company">
            <span class="text-sm">{{ __('I am invoicing as a company') }}</span>
        </label>

        @if ($is_company)
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium">{{ __('Company name') }}</label>
                    <input type="text" wire:model="company_name" class="mt-1 block w-full rounded border-zinc-300">
                </div>
                <div>
                    <label class="block text-sm font-medium">{{ __('Tax ID / VAT') }}</label>
                    <input type="text" wire:model="tax_id" class="mt-1 block w-full rounded border-zinc-300 font-mono">
                </div>
            </div>
        @endif

        <div class="space-y-2 rounded border border-zinc-200 p-4">
            <label class="flex items-start gap-2">
                <input type="checkbox" wire:model="accept_general_terms" class="mt-1">
                <span class="text-sm">{!! __('I accept the :link.', ['link' => '<a target="_blank" class="font-semibold underline" href="'.e($general_terms_url).'">'.e(__('general Terms of Service')).'</a>']) !!}</span>
            </label>
            @error('accept_general_terms') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            <label class="flex items-start gap-2">
                <input type="checkbox" wire:model="accept_affiliate_terms" class="mt-1">
                <span class="text-sm">{!! __('I accept the :link.', ['link' => '<a target="_blank" class="font-semibold underline" href="'.e($affiliate_terms_url).'">'.e(__('Affiliate Program Terms')).'</a>']) !!}</span>
            </label>
            @error('accept_affiliate_terms') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded bg-emerald-600 px-5 py-2.5 font-semibold text-white">{{ __('Submit application') }}</button>
    </form>
</div>
