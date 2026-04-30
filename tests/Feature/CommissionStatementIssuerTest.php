<?php

use A2ZWeb\Affiliate\Events\StatementIssued;
use A2ZWeb\Affiliate\Jobs\GenerateCommissionStatementPdf;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Services\CommissionStatementGenerator;
use A2ZWeb\Affiliate\Services\CommissionStatementIssuer;
use A2ZWeb\Affiliate\Services\CommissionStatementPaymentRecorder;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config()->set('affiliate_statements.issuing_entity.statement_prefix', 'ACS-UK');
});

function makeDraftStatement(): AffiliateCommissionStatement
{
    $u = User::create(['name' => 'X', 'email' => 'iss_'.uniqid().'@x.com', 'password' => bcrypt('x')]);
    $partner = AffiliatePartner::create([
        'user_id' => $u->id,
        'code' => 'C'.$u->id,
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

    return app(CommissionStatementGenerator::class)
        ->generateForPartner($partner, Carbon::create(2026, 3, 1), Carbon::create(2026, 3, 31));
}

it('assigns sequential number, dispatches PDF job and fires event', function () {
    Event::fake([StatementIssued::class]);
    Bus::fake();

    $statement = makeDraftStatement();
    $issued = app(CommissionStatementIssuer::class)->issue($statement);

    expect($issued->payment_status)->toBe('issued');
    expect($issued->statement_number)->toStartWith('ACS-UK-');
    expect($issued->issued_at)->not->toBeNull();

    Bus::assertDispatched(GenerateCommissionStatementPdf::class);
    Event::assertDispatched(StatementIssued::class);
});

it('refuses to issue a zero-commission statement unless allowZero', function () {
    $u = User::create(['name' => 'X', 'email' => 'zero_'.uniqid().'@x.com', 'password' => bcrypt('x')]);
    $partner = AffiliatePartner::create([
        'user_id' => $u->id,
        'code' => 'ZZZ',
        'status' => AffiliatePartner::STATUS_APPROVED,
        'billing_full_name' => 'X',
        'billing_address' => 'Y',
        'country_of_tax_residence' => 'PL',
    ]);
    $statement = app(CommissionStatementGenerator::class)
        ->generateForPartner($partner, Carbon::create(2026, 3, 1), Carbon::create(2026, 3, 31));

    expect(fn () => app(CommissionStatementIssuer::class)->issue($statement))
        ->toThrow(RuntimeException::class);
});

it('mark paid triggers PDF regeneration', function () {
    Event::fake();
    Bus::fake();

    $statement = makeDraftStatement();
    app(CommissionStatementIssuer::class)->issue($statement);
    Bus::assertDispatchedTimes(GenerateCommissionStatementPdf::class, 1);

    app(CommissionStatementPaymentRecorder::class)->markPaid(
        $statement->fresh(),
        'TXN-123',
        Carbon::create(2026, 4, 15),
        'bank_transfer',
    );

    Bus::assertDispatchedTimes(GenerateCommissionStatementPdf::class, 2);
    expect($statement->fresh()->payment_status)->toBe('paid');
    expect($statement->fresh()->payment_reference)->toBe('TXN-123');
});
