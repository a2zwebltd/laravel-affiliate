<?php

use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Services\CommissionStatementGenerator;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config()->set('affiliate_statements.issuing_entity.code', 'uk_ltd');
    config()->set('affiliate_statements.issuing_entity.legal_name', 'A2Z Web Ltd.');
    config()->set('affiliate_statements.issuing_entity.statement_prefix', 'ACS-UK');
    config()->set('affiliate_statements.issuing_entity.tax_status_note', 'Not VAT registered.');
});

it('snapshots issuing entity + affiliate billing onto a new statement', function () {
    $u = User::create(['name' => 'X', 'email' => 'p@x.com']);
    $partner = AffiliatePartner::create([
        'user_id' => $u->id,
        'code' => 'CODE',
        'status' => AffiliatePartner::STATUS_APPROVED,
        'billing_full_name' => 'Dawid M.',
        'billing_address' => 'Street 1',
        'country_of_tax_residence' => 'PL',
    ]);

    AffiliateCommission::create([
        'partner_user_id' => $u->id,
        'referred_user_id' => 999,
        'period_year' => 2026,
        'period_month' => 3,
        'source_amount_cents' => 30000,
        'commission_amount_cents' => 9000,
        'commission_rate_bp' => 3000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);

    $statement = app(CommissionStatementGenerator::class)
        ->generateForPartner($partner, Carbon::create(2026, 3, 1), Carbon::create(2026, 3, 31));

    expect($statement->payment_status)->toBe('draft');
    expect($statement->issuing_entity_legal_name)->toBe('A2Z Web Ltd.');
    expect($statement->issuing_entity_tax_status_note)->toBe('Not VAT registered.');
    expect($statement->affiliate_snapshot)->toMatchArray([
        'billing_full_name' => 'Dawid M.',
        'country_of_tax_residence' => 'PL',
    ]);
    expect($statement->lines)->toHaveCount(1);
    expect((float) $statement->commission_amount)->toBe(90.0);

    // Even after partner edits later, snapshot is preserved.
    $partner->update(['billing_full_name' => 'New Name']);
    expect($statement->fresh()->affiliate_snapshot['billing_full_name'])->toBe('Dawid M.');
});

it('is idempotent for the same partner+period (returns existing non-cancelled)', function () {
    $u = User::create(['name' => 'X', 'email' => 'p2@x.com']);
    $partner = AffiliatePartner::create([
        'user_id' => $u->id,
        'code' => 'CODE2',
        'status' => AffiliatePartner::STATUS_APPROVED,
        'billing_full_name' => 'X',
        'billing_address' => 'Y',
        'country_of_tax_residence' => 'PL',
    ]);
    AffiliateCommission::create([
        'partner_user_id' => $u->id,
        'referred_user_id' => 999,
        'period_year' => 2026,
        'period_month' => 3,
        'source_amount_cents' => 10000,
        'commission_amount_cents' => 3000,
        'commission_rate_bp' => 3000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);

    $g = app(CommissionStatementGenerator::class);
    $a = $g->generateForPartner($partner, Carbon::create(2026, 3, 1), Carbon::create(2026, 3, 31));
    $b = $g->generateForPartner($partner, Carbon::create(2026, 3, 1), Carbon::create(2026, 3, 31));

    expect($a->id)->toBe($b->id);
    expect(AffiliateCommissionStatement::count())->toBe(1);
});
