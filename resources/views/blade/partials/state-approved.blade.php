@php
    $fmt = fn (int $cents) => number_format($cents / 100, 2);
    $cur = strtoupper($currency);
    $available = (int) ($stats['available_to_request_cents'] ?? 0);
    $minPayout = (int) ($stats['min_payout_cents'] ?? 5000);
    $hasPending = (bool) ($stats['has_pending_payout_request'] ?? false);
    $progressPct = min(100, $minPayout > 0 ? ($available / $minPayout) * 100 : 0);
@endphp

{{-- KPI cards --}}
<div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Total earned') }}</div>
        <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-50">{{ $fmt($stats['total_earned_cents']) }}<span class="ml-1 text-base font-medium text-zinc-400">{{ $cur }}</span></div>
        <div class="mt-1 text-xs text-zinc-500">{{ __('Lifetime') }}</div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Last closed month') }}</div>
        <div class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $fmt($stats['last_month_earned_cents']) }}<span class="ml-1 text-base font-medium text-zinc-400">{{ $cur }}</span></div>
        <div class="mt-1 text-xs text-zinc-500">{{ now()->subMonthNoOverflow()->format('F Y') }}</div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center gap-2">
            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Current month so far') }}</div>
            <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ __('in progress') }}</span>
        </div>
        <div class="mt-2 text-3xl font-bold text-zinc-500 dark:text-zinc-400">{{ $fmt($stats['current_month_running_cents'] ?? 0) }}<span class="ml-1 text-base font-medium text-zinc-400">{{ $cur }}</span></div>
        <div class="mt-1 text-xs text-zinc-500">{{ now()->format('F Y') }} · {{ __('not yet closed') }}</div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Active paying referrals') }}</div>
        <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-50">{{ $stats['active_paying_referrals'] }}</div>
        <div class="mt-1 text-xs text-zinc-500">{{ __('of :total total', ['total' => $stats['total_referrals']]) }}</div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between">
            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Available to request') }}</div>
            @if ($hasPending)
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ __('PENDING REQUEST') }}</span>
            @endif
        </div>
        <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-50">{{ $fmt($available) }}<span class="ml-1 text-base font-medium text-zinc-400">{{ $cur }}</span></div>
        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
            <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500" style="width: {{ $progressPct }}%"></div>
        </div>
        <div class="mt-1 text-xs text-zinc-500">{{ __('Min. payout :amount :currency', ['amount' => $fmt($minPayout), 'currency' => $cur]) }}</div>
    </div>
</div>

{{-- Earnings chart + Request a payout (side by side on lg+) --}}
<div class="mt-8 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-baseline justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Earnings per month') }}</h2>
            <div class="text-xs text-zinc-500">{{ __('Gross commission per month') }}</div>
        </div>

        @php
            $maxMonth = max(1, (int) (collect($stats['monthly'])->max('gross_cents') ?: 1));
        @endphp
        <div class="mt-6 flex h-44 items-end gap-2">
            @foreach ($stats['monthly'] as $m)
                @php
                    $h = max(2, intval(($m['gross_cents'] / $maxMonth) * 100));
                    $isCurrent = ! empty($m['is_current']);
                    $titleSuffix = $isCurrent ? ' ('.__('in progress').')' : '';
                    $barClasses = $isCurrent
                        ? 'bg-zinc-300 dark:bg-zinc-600'
                        : 'bg-gradient-to-t from-emerald-600 to-teal-400 dark:from-emerald-500 dark:to-teal-300';
                @endphp
                <div class="group flex h-full flex-1 flex-col items-center justify-end gap-1" title="{{ \Carbon\Carbon::create($m['year'], $m['month'], 1)->format('M Y') }}: {{ $fmt($m['gross_cents']) }} {{ $cur }}{{ $titleSuffix }}">
                    <div class="text-[10px] font-mono text-zinc-500 opacity-0 transition group-hover:opacity-100">{{ $fmt($m['gross_cents']) }}</div>
                    <div class="w-full rounded-t {{ $barClasses }} transition-all" style="height: {{ $h }}%"></div>
                    <div class="text-[10px] {{ $isCurrent ? 'font-semibold text-zinc-500' : 'text-zinc-500' }}">{{ \Carbon\Carbon::create($m['year'], $m['month'], 1)->format('M') }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">{{ __('Request a payout') }}</h3>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{!! __('Available now: :amount', ['amount' => '<strong class="text-zinc-900 dark:text-zinc-100">'.e($fmt($available).' '.$cur).'</strong>']) !!}</p>

        @if ($hasPending)
            <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ __('You already have an open payout request.') }}</p>
        @elseif ($available < $minPayout)
            <p class="mt-3 rounded-md bg-zinc-50 px-3 py-2 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ __('You need at least :amount :currency to request a payout.', ['amount' => $fmt($minPayout), 'currency' => $cur]) }}</p>
        @elseif (! $partner->payoutDetailsComplete())
            <p class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:bg-rose-900/30 dark:text-rose-300">{{ __('Add your payout details first.') }}</p>
        @else
            <form method="POST" action="{{ route(config('affiliate.routes.name_prefix', 'affiliate.').'payouts.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Invoice or receipt PDF') }} <span class="text-zinc-400">{{ __('(optional)') }}</span></label>
                    <input type="file" name="invoice" accept="application/pdf" class="mt-1 block w-full text-xs text-zinc-700 file:mr-2 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-300 dark:file:bg-zinc-800 dark:file:text-zinc-300">
                    <p class="mt-1 text-[10px] text-zinc-400">{{ __('Max 5 MB. PDF only.') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Purchase order / reference') }} <span class="text-zinc-400">{{ __('(optional)') }}</span></label>
                    <input type="text" name="purchase_order_id" maxlength="191" class="mt-1 block w-full rounded-md border border-zinc-300 px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                </div>
                <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    {{ __('Request payout') }}
                </button>
            </form>
        @endif

        <div class="mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-800">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Payout method') }}</h4>
            <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                @if ($partner->payout_method === 'paypal')
                    {{ __('PayPal') }}: <span class="font-mono">{{ $partner->paypal_email }}</span>
                @elseif ($partner->payout_method === 'bank_transfer')
                    {{ __('Bank transfer') }}: <span class="font-mono">{{ $partner->bank_iban }}</span>
                @else
                    {{ __('Not set') }}
                @endif
            </p>
            <a href="{{ route(config('affiliate.routes.name_prefix', 'affiliate.').'apply.show') }}" class="mt-2 inline-block text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-400">{{ __('Edit payout details →') }}</a>
        </div>
    </div>
</div>

{{-- Referrals table --}}
<div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
    <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Your referrals') }}</h2>
        <p class="mt-1 text-xs text-zinc-500">{{ __('Privacy: we never disclose what your referrals do — only whether they pay and how much you earned.') }}</p>
    </div>
    @if (empty($stats['referrals']))
        <div class="px-6 py-10 text-center text-sm text-zinc-500">{{ __('No referrals yet — share your link to get started.') }}</div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-800/40">
                    <tr>
                        <th class="px-6 py-3">{{ __('Account') }}</th>
                        <th class="px-6 py-3">{{ __('Joined') }}</th>
                        <th class="px-6 py-3">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Earned (12 mo)') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($stats['referrals'] as $r)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $r['display_name'] }}</td>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ \Carbon\Carbon::parse($r['attributed_at'])->format('M j, Y') }}</td>
                            <td class="px-6 py-3">
                                @if ($r['is_paying'])
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span> {{ __('Paying') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ __('Free') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right font-mono text-zinc-900 dark:text-zinc-100">{{ $fmt($r['gross_last_12mo_cents']) }} {{ $cur }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Adjustments (admin-applied corrections, openly disclosed) --}}
@if (! empty($stats['adjustments']))
    <div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Adjustments') }}</h2>
            <p class="mt-1 text-xs text-zinc-500">{{ __('Manual corrections applied to your base revenue. Each adjusts the source revenue for a specific month and is paid at your commission rate.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-800/40">
                    <tr>
                        <th class="px-6 py-3">{{ __('Period') }}</th>
                        <th class="px-6 py-3">{{ __('Type') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Base Δ') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Commission Δ') }}</th>
                        <th class="px-6 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($stats['adjustments'] as $a)
                        @php
                            $signClass = $a['commission_cents'] >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400';
                            $sign = $a['commission_cents'] >= 0 ? '+' : '−';
                            $statusBadge = match ($a['status']) {
                                'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                                'requested' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                            };
                        @endphp
                        <tr>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ \Carbon\Carbon::create($a['year'], $a['month'], 1)->format('F Y') }}</td>
                            <td class="px-6 py-3 capitalize text-zinc-700 dark:text-zinc-300">{{ $a['type'] }}</td>
                            <td class="px-6 py-3 text-right font-mono {{ $signClass }}">{{ $sign }}{{ number_format(abs($a['base_cents']) / 100, 2) }} {{ $cur }}</td>
                            <td class="px-6 py-3 text-right font-mono {{ $signClass }}">{{ $sign }}{{ number_format(abs($a['commission_cents']) / 100, 2) }} {{ $cur }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize {{ $statusBadge }}">{{ $a['status'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- Payouts & statements (combined) --}}
<div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
    <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Payouts') }}</h2>
        <p class="mt-1 text-xs text-zinc-500">{{ __('Each row is one payout request. The statement column links to the formal commission statement (PDF) once the payout is paid.') }}</p>
    </div>
    @if ($payoutRequests->isEmpty())
        <div class="px-6 py-8 text-center text-sm text-zinc-500">{{ __('No payout requests yet.') }}</div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-800/40">
                    <tr>
                        <th class="px-6 py-3">{{ __('Requested') }}</th>
                        <th class="px-6 py-3">{{ __('Period') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Net') }}</th>
                        <th class="px-6 py-3">{{ __('Status') }}</th>
                        <th class="px-6 py-3">{{ __('Reference') }}</th>
                        <th class="px-6 py-3">{{ __('Statement') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($payoutRequests as $pr)
                        @php
                            $badge = match ($pr->status) {
                                'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                'approved' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                                'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
                                default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                            };
                            $statement = $pr->statements->first();
                        @endphp
                        <tr>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $pr->requested_at->format('M j, Y') }}</td>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $pr->period_start->format('M Y') }} → {{ $pr->period_end->format('M Y') }}</td>
                            <td class="px-6 py-3 text-right font-mono text-zinc-900 dark:text-zinc-100">{{ $fmt($pr->net_amount_cents) }} {{ $cur }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize {{ $badge }}">{{ $pr->status }}</span>
                            </td>
                            <td class="px-6 py-3 font-mono text-xs text-zinc-500">{{ $pr->payment_reference ?? '—' }}</td>
                            <td class="px-6 py-3">
                                @if ($statement)
                                    <a href="{{ route('affiliate.statements.show', $statement) }}" class="font-mono text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-400">{{ $statement->statement_number }}</a>
                                    @if ($statement->pdf_path)
                                        <a href="{{ route('affiliate.statements.download', $statement) }}" class="ml-2 text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-400">{{ __('PDF ↓') }}</a>
                                    @else
                                        <span class="ml-2 text-xs text-zinc-400" title="{{ __('PDF queued, refresh in a moment') }}">{{ __('PDF…') }}</span>
                                    @endif
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                @if ($pr->status === 'pending')
                                    <form method="POST" action="{{ route(config('affiliate.routes.name_prefix', 'affiliate.').'payouts.cancel', $pr) }}" onsubmit="return confirm('{{ __('Cancel this payout request?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-semibold text-rose-600 hover:underline">{{ __('Cancel') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
