<div class="mt-8 grid gap-6 lg:grid-cols-3">
    {{-- Eligibility --}}
    <div class="lg:col-span-2 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Become an Affiliate Partner') }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Invite at least :min signups to unlock the application form.', ['min' => $min_referred_users]) }}</p>
            </div>
            @if ($eligible)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span> {{ __('Eligible to apply') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                    {{ __(':count more referral(s) needed', ['count' => $referrals_needed]) }}
                </span>
            @endif
        </div>

        <div class="mt-6">
            <div class="flex justify-between text-xs font-medium text-zinc-500">
                <span>{{ __('Progress') }}</span>
                <span>{{ $referred_count }} / {{ $min_referred_users }}</span>
            </div>
            <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all duration-700" style="width: {{ min(100, ($referred_count / max(1, $min_referred_users)) * 100) }}%"></div>
            </div>
        </div>

        <div class="mt-6">
            @if ($eligible)
                <a href="{{ route(config('affiliate.routes.name_prefix', 'affiliate.').'apply.show') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    {{ __('Apply now →') }}
                </a>
            @else
                <button type="button" disabled class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg bg-zinc-200 px-5 py-3 text-sm font-semibold text-zinc-500 dark:bg-zinc-800">
                    {{ __('Apply now (locked)') }}
                </button>
            @endif
        </div>
    </div>

    {{-- Why apply --}}
    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">{{ __('Why join') }}</h3>
        <ul class="mt-4 space-y-3 text-sm text-zinc-700 dark:text-zinc-300">
            <li class="flex items-start gap-2">
                <span class="mt-0.5 size-1.5 shrink-0 rounded-full bg-emerald-500"></span>
                <span><strong class="font-semibold">{{ __('Lifetime commission') }}</strong> — {{ __('earn on every closed month your referrals stay active.') }}</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-0.5 size-1.5 shrink-0 rounded-full bg-emerald-500"></span>
                <span><strong class="font-semibold">{{ __('Transparent stats') }}</strong> — {{ __('monthly summaries and earnings breakdown.') }}</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-0.5 size-1.5 shrink-0 rounded-full bg-emerald-500"></span>
                <span><strong class="font-semibold">{{ __('PayPal or bank transfer') }}</strong> — {{ __('your choice, your schedule.') }}</span>
            </li>
        </ul>
    </div>
</div>
