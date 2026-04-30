<div class="mt-8 rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-900 shadow-sm dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200">
    <h2 class="text-xl font-semibold">{{ __('Application not approved') }}</h2>
    @if (! empty($reason))
        <p class="mt-1 text-sm opacity-90">{{ $reason }}</p>
    @else
        <p class="mt-1 text-sm opacity-90">{{ __('Your application has not been approved at this time. You may re-apply later.') }}</p>
    @endif
</div>
