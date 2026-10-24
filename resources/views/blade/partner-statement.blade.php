@extends(config('affiliate.layout'))

@section('title', __('Statement :number', ['number' => $statement->statement_number]))

@section('content')
@php
    $cur = strtoupper((string) $statement->currency);
    $rate = number_format(((float) $statement->commission_rate) * 100, 2).'%';
    $fmt = fn ($v) => number_format((float) $v, 2);
    $aff = (object) ($snapshot ?? []);
    $badge = match ($statement->payment_status) {
        'issued' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    };
@endphp

<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
    <a href="{{ route('affiliate.dashboard') }}" class="text-sm font-semibold text-zinc-600 hover:underline dark:text-zinc-400">{{ __('← Back to dashboard') }}</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('Statement :number', ['number' => $statement->statement_number]) }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $statement->period_start?->translatedFormat('M j, Y') }} – {{ $statement->period_end?->translatedFormat('M j, Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize {{ $badge }}">{{ __($statement->payment_status) }}</span>
            @if ($statement->pdf_path)
                <a href="{{ route('affiliate.statements.download', $statement) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    {{ __('Download PDF') }}
                </a>
            @else
                <span class="rounded-lg bg-zinc-200 px-4 py-2 text-sm font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400" title="{{ __('The PDF is being generated — refresh in a moment.') }}">
                    {{ __('PDF generating…') }}
                </span>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Issuing entity') }}</h2>
            <p class="mt-2 font-semibold text-zinc-900 dark:text-zinc-100">{{ $statement->issuing_entity_legal_name }}</p>
            @if ($statement->issuing_entity_address)
                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $statement->issuing_entity_address }}</p>
            @endif
            @if ($statement->issuing_entity_country)
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $statement->issuing_entity_country }}</p>
            @endif
            @if ($statement->issuing_entity_company_number)
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $statement->issuing_entity_company_number_label ?: __('Company Number') }}: {{ $statement->issuing_entity_company_number }}</p>
            @endif
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Affiliate (you)') }}</h2>
            <p class="mt-2 font-semibold text-zinc-900 dark:text-zinc-100">{{ $aff->billing_full_name ?? '—' }}</p>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">{!! nl2br(e($aff->billing_address ?? '')) !!}</p>
            @if (! empty($aff->country_of_tax_residence))
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Country of tax residence:') }} {{ $aff->country_of_tax_residence }}</p>
            @endif
        </div>
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Calculation · :rate commission rate', ['rate' => $rate]) }}</h2>
        </div>
        <table class="w-full text-sm text-zinc-800 dark:text-zinc-200">
            <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-2">{{ __('Date') }}</th>
                    <th class="px-5 py-2">{{ __('Customer') }}</th>
                    <th class="px-5 py-2">{{ __('Subscription') }}</th>
                    <th class="px-5 py-2 text-right">{{ __('Gross (:cur)', ['cur' => $cur]) }}</th>
                    <th class="px-5 py-2 text-right">{{ __('Commission (:cur)', ['cur' => $cur]) }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($statement->lines as $line)
                    <tr>
                        <td class="px-5 py-2">{{ $line->transaction_date?->format('Y-m-d') }}</td>
                        <td class="px-5 py-2">{{ $line->customer_reference }}</td>
                        <td class="px-5 py-2 text-zinc-500 dark:text-zinc-400">{{ $line->subscription_or_invoice_reference }}</td>
                        <td class="px-5 py-2 text-right font-mono">{{ $fmt($line->gross_amount) }}</td>
                        <td class="px-5 py-2 text-right font-mono">{{ $fmt($line->line_commission) }}</td>
                    </tr>
                @endforeach
                <tr class="bg-zinc-50 font-semibold dark:bg-zinc-800/60">
                    <td colspan="3" class="px-5 py-2 text-right">{{ __('Total') }}</td>
                    <td class="px-5 py-2 text-right font-mono">{{ $fmt($statement->gross_revenue_total) }}</td>
                    <td class="px-5 py-2 text-right font-mono">{{ $fmt($statement->commission_amount) }} {{ $cur }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if ($statement->payment_status === 'paid')
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-200">
            <h2 class="text-sm font-semibold">{{ __('Payment') }}</h2>
            <p class="mt-2 text-sm">
                {{ __(':method on :date, reference :reference.', [
                    'method' => ucfirst(str_replace('_', ' ', (string) $statement->payment_method)),
                    'date' => optional($statement->payment_date)->toDateString(),
                    'reference' => $statement->payment_reference,
                ]) }}
            </p>
        </div>
    @endif

    <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200">
        {{ $statement->issuing_entity_tax_status_note }} {{ __('The affiliate is responsible for declaring this income in their jurisdiction of tax residence.') }}
    </div>
</div>
@endsection
