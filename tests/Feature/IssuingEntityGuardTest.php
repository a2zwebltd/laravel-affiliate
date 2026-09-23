<?php

use A2ZWeb\Affiliate\Exceptions\IssuingEntityNotConfigured;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use A2ZWeb\Affiliate\Services\CommissionStatementGenerator;
use A2ZWeb\Affiliate\Services\CommissionStatementIssuer;
use A2ZWeb\Affiliate\Services\PayoutCompletionWorkflow;
use A2ZWeb\Affiliate\Services\PayoutRequestService;
use A2ZWeb\Affiliate\Support\IssuingEntity;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

function guardPartner(): AffiliatePartner
{
    $u = User::create(['email' => 'guard_'.uniqid().'@x.com']);
    AffiliateCommission::create([
        'partner_user_id' => $u->id,
        'referred_user_id' => 777,
        'period_year' => 2026,
        'period_month' => 3,
        'source_amount_cents' => 10000,
        'commission_amount_cents' => 3000,
        'commission_rate_bp' => 3000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);

    return AffiliatePartner::create([
        'user_id' => $u->id,
        'code' => 'G'.$u->id,
        'status' => AffiliatePartner::STATUS_APPROVED,
    ]);
}

function generateMarch(AffiliatePartner $partner): AffiliateCommissionStatement
{
    return app(CommissionStatementGenerator::class)
        ->generateForPartner($partner, Carbon::create(2026, 3, 1), Carbon::create(2026, 3, 31));
}

it('ships no placeholder legal entity defaults', function () {
    $defaults = require __DIR__.'/../../config/affiliate_statements.php';

    expect($defaults['issuing_entity']['code'])->toBeNull();
    expect($defaults['issuing_entity']['legal_name'])->toBeNull();
    expect($defaults['issuing_entity']['address'])->toBeNull();
    expect($defaults['issuing_entity']['company_number'])->toBeNull();
});

it('refuses to generate a statement while the legal entity is not configured, and logs it', function () {
    config()->set('affiliate_statements.issuing_entity.legal_name', null);
    Log::spy();

    $partner = guardPartner();

    expect(fn () => generateMarch($partner))
        ->toThrow(IssuingEntityNotConfigured::class, 'issuing_entity.legal_name is empty');

    expect(AffiliateCommissionStatement::count())->toBe(0);
    Log::shouldHaveReceived('error')->once();
});

it('refuses the old Acme placeholders from a previously published config', function () {
    config()->set('affiliate_statements.issuing_entity', [
        'code' => 'acme_ltd',
        'legal_name' => 'Acme Ltd.',
        'company_number' => '00000000',
        'address' => '123 Example Street, Example City, EX1 2AB',
        'country' => 'United Kingdom',
        'statement_prefix' => 'ACS',
    ]);

    expect(fn () => generateMarch(guardPartner()))->toThrow(IssuingEntityNotConfigured::class);
    expect(AffiliateCommissionStatement::count())->toBe(0);
});

it('refuses to issue a draft that froze placeholder values before the upgrade', function () {
    Bus::fake();
    $draft = generateMarch(guardPartner());
    $draft->forceFill(['issuing_entity' => 'acme_ltd', 'issuing_entity_legal_name' => 'Acme Ltd.'])->save();

    expect(fn () => app(CommissionStatementIssuer::class)->issue($draft))
        ->toThrow(IssuingEntityNotConfigured::class);

    expect($draft->fresh()->payment_status)->toBe(AffiliateCommissionStatement::STATUS_DRAFT);
    Bus::assertNothingDispatched();
});

it('accepts an entity with only code and legal name set', function () {
    Bus::fake();
    config()->set('affiliate_statements.issuing_entity', [
        'code' => 'uk_ltd',
        'legal_name' => 'Real Company Ltd',
        'company_number' => '',
        'address' => '',
        'country' => 'United Kingdom',
        'tax_status_note' => 'Not VAT registered.',
        'statement_prefix' => 'ACS',
    ]);

    $issued = app(CommissionStatementIssuer::class)->issue(generateMarch(guardPartner()));

    expect($issued->payment_status)->toBe(AffiliateCommissionStatement::STATUS_ISSUED);
    expect($issued->issuing_entity_legal_name)->toBe('Real Company Ltd');
});

it('lists every problem it finds', function () {
    expect(IssuingEntity::problems(['code' => '', 'legal_name' => 'Acme Ltd.']))->toBe([
        'issuing_entity.code is empty',
        'issuing_entity.legal_name still holds the placeholder default',
    ]);
    expect(IssuingEntity::problems(['code' => 'x', 'legal_name' => 'Y Ltd']))->toBe([]);
});

it('does not mark a payout paid when the statement could not be issued', function () {
    Notification::fake();
    config()->set('affiliate.min_payout_cents', 1000);

    $partner = guardPartner();
    $partner->update([
        'payout_method' => AffiliatePartner::PAYOUT_PAYPAL,
        'paypal_email' => 'pp@x.com',
    ]);
    $admin = User::create(['email' => 'admin_'.uniqid().'@x.com']);

    $service = app(PayoutRequestService::class);
    $request = $service->create($partner->fresh());
    $service->approve($request, $admin);

    config()->set('affiliate_statements.issuing_entity.legal_name', null);

    expect(fn () => app(PayoutCompletionWorkflow::class)->complete($request->fresh(), $admin, 'REF-1', Carbon::now()))
        ->toThrow(IssuingEntityNotConfigured::class);

    expect($request->fresh()->status)->toBe(AffiliatePayoutRequest::STATUS_APPROVED);
    expect(AffiliateCommissionStatement::count())->toBe(0);
});
