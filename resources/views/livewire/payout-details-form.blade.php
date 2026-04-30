<div>
    <form wire:submit="save" class="space-y-4">
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
            </div>
        @else
            <div>
                <label class="block text-sm font-medium">{{ __('Account holder') }}</label>
                <input type="text" wire:model="bank_account_holder" class="mt-1 block w-full rounded border-zinc-300">
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('IBAN') }}</label>
                <input type="text" wire:model="bank_iban" class="mt-1 block w-full rounded border-zinc-300 font-mono">
            </div>
        @endif
        <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">{{ __('Save') }}</button>
    </form>
</div>
