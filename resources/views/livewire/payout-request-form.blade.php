<div>
    @error('payout') <p class="mb-2 text-xs text-rose-600">{{ $message }}</p> @enderror
    <form wire:submit="submit" class="space-y-3">
        <div>
            <label class="block text-xs font-medium">{{ __('Invoice PDF (optional)') }}</label>
            <input type="file" wire:model="invoice" accept="application/pdf" class="mt-1 text-xs">
            @error('invoice') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium">{{ __('Purchase order (optional)') }}</label>
            <input type="text" wire:model="purchase_order_id" maxlength="191" class="mt-1 block w-full rounded-md border-zinc-300 text-xs">
        </div>
        <button type="submit" wire:loading.attr="disabled" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {{ __('Request payout') }}
        </button>
    </form>
</div>
