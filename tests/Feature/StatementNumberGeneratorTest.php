<?php

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use A2ZWeb\Affiliate\Services\StatementNumberGenerator;
use Illuminate\Support\Carbon;

it('starts from 0001 when no prior statements', function () {
    $g = app(StatementNumberGenerator::class);
    $next = $g->nextInTransaction('ACS-UK', Carbon::create(2026, 4, 15));
    expect($next)->toBe('ACS-UK-2026-0001');
});

it('increments based on the latest existing statement number', function () {
    AffiliateCommissionStatement::query()->insert([
        [
            'statement_number' => 'ACS-UK-2026-0007',
            'issuing_entity' => 'uk_ltd',
            'issuing_entity_legal_name' => 'X',
            'issuing_entity_statement_prefix' => 'ACS-UK',
            'partner_user_id' => 1,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'currency' => 'usd',
            'commission_rate' => 0.30,
            'gross_revenue_total' => 0,
            'commission_amount' => 0,
            'payment_status' => 'issued',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $g = app(StatementNumberGenerator::class);
    expect($g->nextInTransaction('ACS-UK', Carbon::create(2026, 4, 15)))->toBe('ACS-UK-2026-0008');
});

it('keeps separate counters per prefix', function () {
    AffiliateCommissionStatement::query()->insert([
        [
            'statement_number' => 'ACS-UK-2026-0009',
            'issuing_entity' => 'uk_ltd',
            'issuing_entity_legal_name' => 'X',
            'issuing_entity_statement_prefix' => 'ACS-UK',
            'partner_user_id' => 1,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'currency' => 'usd',
            'commission_rate' => 0.30,
            'gross_revenue_total' => 0,
            'commission_amount' => 0,
            'payment_status' => 'issued',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $g = app(StatementNumberGenerator::class);
    expect($g->nextInTransaction('ACS-SG', Carbon::create(2026, 4, 15)))->toBe('ACS-SG-2026-0001');
});
