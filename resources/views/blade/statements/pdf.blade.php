<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $statement->statement_number }}</title>
    <style>
        @page { margin: 24mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1f2937; }
        h1 { font-size: 18pt; margin: 0 0 4mm; color: #111827; }
        h2 { font-size: 11pt; margin: 6mm 0 2mm; text-transform: uppercase; letter-spacing: 0.5pt; color: #374151; }
        .muted { color: #6b7280; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        th { background: #f3f4f6; text-align: left; padding: 2mm; border-bottom: 0.4pt solid #e5e7eb; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #4b5563; }
        td { padding: 2mm; border-bottom: 0.3pt solid #f3f4f6; font-size: 9.5pt; }
        td.num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .totals td { font-weight: bold; border-top: 0.6pt solid #111827; }
        .grand td { font-size: 12pt; }
        .header-row { width: 100%; }
        .header-row td { vertical-align: top; padding: 0; border: 0; }
        .header-right { text-align: right; }
        .box { background: #f9fafb; padding: 4mm; border-radius: 2mm; }
        .tax-box { border: 0.5pt solid #e5e7eb; padding: 3mm; background: #fffbeb; font-size: 9pt; }
        .footer { margin-top: 10mm; padding-top: 4mm; border-top: 0.4pt solid #e5e7eb; font-size: 8pt; color: #9ca3af; }
    </style>
</head>
<body>
@php
    $entity = (object) [
        'name' => $statement->issuing_entity_legal_name,
        'address' => $statement->issuing_entity_address,
        'country' => $statement->issuing_entity_country,
        'company_number' => $statement->issuing_entity_company_number,
        'company_number_label' => $statement->issuing_entity_company_number_label ?: __('Company Number'),
        'tax_status' => $statement->issuing_entity_tax_status_note,
    ];
    $aff = (object) ($statement->affiliate_snapshot ?? []);
    $cur = strtoupper($statement->currency);
    $fmt = fn ($v) => number_format((float) $v, 2);
    $rate = number_format(((float) $statement->commission_rate) * 100, 2).'%';
@endphp

<table class="header-row">
    <tr>
        <td>
            <strong>{{ $entity->name }}</strong><br>
            <span class="muted">{!! nl2br(e($entity->address ?? '')) !!}</span><br>
            <span class="muted">{{ $entity->country }}</span><br>
            <span class="muted">{{ $entity->company_number_label }}: {{ $entity->company_number }}</span>
        </td>
        <td class="header-right">
            <h1>{{ __('Affiliate Commission Statement') }}</h1>
            <strong>{{ $statement->statement_number }}</strong><br>
            <span class="muted">{{ __('Issue date:') }} {{ optional($statement->issued_at)->toDateString() ?? optional($statement->created_at)->toDateString() }}</span><br>
            <span class="muted">{{ __('Period:') }} {{ $statement->period_start->format('M j, Y') }} – {{ $statement->period_end->format('M j, Y') }}</span>
        </td>
    </tr>
</table>

<h2>{{ __('Affiliate') }}</h2>
<div class="box">
    <strong>{{ $aff->billing_full_name ?? ($aff->company_name ?? '—') }}</strong><br>
    {!! nl2br(e($aff->billing_address ?? '')) !!}<br>
    @if (!empty($aff->country_of_tax_residence))
        <span class="muted">{{ __('Country of tax residence:') }} {{ $aff->country_of_tax_residence }}</span><br>
    @endif
    @if (!empty($aff->contact_email))
        <span class="muted">{{ __('Email:') }} {{ $aff->contact_email }}</span><br>
    @endif
    @if (!empty($aff->tax_id))
        <span class="muted">{{ __('Tax ID:') }} {{ $aff->tax_id }}</span><br>
    @endif
    <span class="muted">{{ __('Affiliate ID: #:id · code: :code', ['id' => $statement->partner_user_id, 'code' => $aff->partner_code ?? '—']) }}</span>
</div>

<h2>{{ __('Commission basis') }}</h2>
<div>
    {{ __('Recurring revenue share on referred customer revenue.') }}
    @if (!empty($agreement['version']))
        {{ __('Affiliate Agreement v:version', ['version' => $agreement['version']]) }}@if (!empty($agreement['date'])) {{ __('(dated :date)', ['date' => $agreement['date']]) }}@endif.
    @endif
    <br>
    <span class="muted">{{ __('Commission rate: :rate of paid revenue from referred :product.', ['rate' => $rate, 'product' => $agreement['product_name'] ?? __('subscriptions')]) }}</span>
</div>

<h2>{{ __('Calculation') }}</h2>
<table>
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Customer ref.') }}</th>
            <th>{{ __('Subscription ref.') }}</th>
            <th class="num">{{ __('Gross (:cur)', ['cur' => $cur]) }}</th>
            <th class="num">{{ __('Rate') }}</th>
            <th class="num">{{ __('Commission (:cur)', ['cur' => $cur]) }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lines as $line)
            <tr>
                <td>{{ $line->transaction_date->format('Y-m-d') }}</td>
                <td>{{ $line->customer_reference }}</td>
                <td>{{ $line->subscription_or_invoice_reference ?? '—' }}</td>
                <td class="num">{{ $fmt($line->gross_amount) }}</td>
                <td class="num">{{ number_format(((float) $line->commission_rate) * 100, 2) }}%</td>
                <td class="num">{{ $fmt($line->line_commission) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">{{ __('No lines.') }}</td></tr>
        @endforelse
        <tr class="totals">
            <td colspan="3">{{ __('Subtotal') }}</td>
            <td class="num">{{ $fmt($statement->gross_revenue_total) }}</td>
            <td></td>
            <td class="num">{{ $fmt($statement->commission_amount) }}</td>
        </tr>
        <tr class="totals grand">
            <td colspan="5">{{ __('Total commission due') }}</td>
            <td class="num">{{ $fmt($statement->commission_amount) }} {{ $cur }}</td>
        </tr>
    </tbody>
</table>

<h2>{{ __('Payment') }}</h2>
@if ($statement->payment_status === 'paid')
    <div class="box">
        <strong>{{ __('Method:') }}</strong> {{ str_replace('_', ' ', $statement->payment_method) }}<br>
        <strong>{{ __('Date:') }}</strong> {{ optional($statement->payment_date)->toDateString() }}<br>
        <strong>{{ __('Reference:') }}</strong> <code>{{ $statement->payment_reference }}</code>
    </div>
@else
    <div class="muted">{{ __('Payment pending. This statement will be reissued with the payment reference once the transfer is completed.') }}</div>
@endif

<h2>{{ __('Tax status') }}</h2>
<div class="tax-box">
    {{ $entity->tax_status }}
    {{ __('The affiliate is responsible for declaring this income and complying with tax obligations in their jurisdiction of tax residence. The issuing entity does not withhold any tax on this payment.') }}
</div>

<h2>{{ __('Acknowledgment') }}</h2>
<div class="muted">
    {{ __('This statement confirms a commission payment under the Affiliate Agreement between :entity and the affiliate identified above. By accepting the payment referenced herein, the affiliate acknowledges receipt of the amount stated.', ['entity' => $entity->name]) }}
</div>

<div class="footer">
    {{ $entity->name }} · {{ $entity->company_number_label }}: {{ $entity->company_number }} · {{ $entity->country }}<br>
    {{ __('Statement :number · generated :timestamp', ['number' => $statement->statement_number, 'timestamp' => now()->toIso8601String()]) }}
</div>

</body>
</html>
