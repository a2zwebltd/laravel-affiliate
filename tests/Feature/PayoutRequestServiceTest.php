<?php

use A2ZWeb\Affiliate\Models\AffiliateAdjustment;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Services\PayoutRequestService;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;

function makeApprovedPartnerUser(): User
{
    $u = User::create(['name' => 'X', 'email' => uniqid().'@x.com']);
    AffiliatePartner::create([
        'user_id' => $u->id,
        'code' => 'CODE'.$u->id,
        'status' => AffiliatePartner::STATUS_APPROVED,
        'payout_method' => AffiliatePartner::PAYOUT_PAYPAL,
        'paypal_email' => 'pp@x.com',
    ]);

    return $u;
}

it('creates a payout request including adjustments and locks rows', function () {
    $u = makeApprovedPartnerUser();

    $referred = User::create(['name' => 'X', 'email' => 'r@x.com']);
    AffiliateCommission::create([
        'partner_user_id' => $u->id,
        'referred_user_id' => $referred->id,
        'period_year' => 2026,
        'period_month' => 2,
        'source_amount_cents' => 20000,
        'commission_amount_cents' => 6000,
        'commission_rate_bp' => 3000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
        'closed_at' => Carbon::now(),
    ]);

    AffiliateAdjustment::create([
        'partner_user_id' => $u->id,
        'period_year' => 2026,
        'period_month' => 2,
        'type' => AffiliateAdjustment::TYPE_SUBTRACTION,
        'amount_cents' => 500,
        'currency' => 'usd',
        'reason' => 'admin tweak',
        'admin_user_id' => 1,
        'status' => AffiliateAdjustment::STATUS_CLOSED,
    ]);

    config()->set('affiliate.min_payout_cents', 1000);

    $request = app(PayoutRequestService::class)->create($u->affiliatePartner);

    expect($request->status)->toBe('pending');
    expect($request->gross_amount_cents)->toBe(6000);
    // -500 base × 3000bp / 10000 = -150 commission delta
    expect($request->adjustments_amount_cents)->toBe(-150);
    expect($request->net_amount_cents)->toBe(5850);

    expect(AffiliateCommission::first()->status)->toBe('requested');
    expect(AffiliateAdjustment::first()->status)->toBe('requested');
});

it('refuses request when below minimum payout', function () {
    $u = makeApprovedPartnerUser();
    $referred = User::create(['name' => 'X', 'email' => 'r@x.com']);
    AffiliateCommission::create([
        'partner_user_id' => $u->id,
        'referred_user_id' => $referred->id,
        'period_year' => 2026,
        'period_month' => 2,
        'source_amount_cents' => 1000,
        'commission_amount_cents' => 100,
        'commission_rate_bp' => 3000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);
    config()->set('affiliate.min_payout_cents', 5000);

    expect(fn () => app(PayoutRequestService::class)->create($u->affiliatePartner))
        ->toThrow(RuntimeException::class);
});

it('marking as paid moves commissions and adjustments to paid', function () {
    $u = makeApprovedPartnerUser();
    $referred = User::create(['name' => 'X', 'email' => 'r@x.com']);
    AffiliateCommission::create([
        'partner_user_id' => $u->id,
        'referred_user_id' => $referred->id,
        'period_year' => 2026,
        'period_month' => 2,
        'source_amount_cents' => 20000,
        'commission_amount_cents' => 6000,
        'commission_rate_bp' => 3000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);
    config()->set('affiliate.min_payout_cents', 1000);

    $service = app(PayoutRequestService::class);
    $admin = User::create(['name' => 'X', 'email' => 'admin@x.com']);

    $request = $service->create($u->affiliatePartner);
    $service->approve($request, $admin);
    $service->markPaid($request, $admin, 'TXN123');

    $request->refresh();
    expect($request->status)->toBe('paid');
    expect(AffiliateCommission::first()->status)->toBe('paid');
});

it('cancelling a pending request reverts commissions to closed', function () {
    $u = makeApprovedPartnerUser();
    $referred = User::create(['name' => 'X', 'email' => 'r@x.com']);
    AffiliateCommission::create([
        'partner_user_id' => $u->id,
        'referred_user_id' => $referred->id,
        'period_year' => 2026,
        'period_month' => 2,
        'source_amount_cents' => 20000,
        'commission_amount_cents' => 6000,
        'commission_rate_bp' => 3000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);
    config()->set('affiliate.min_payout_cents', 1000);

    $service = app(PayoutRequestService::class);
    $request = $service->create($u->affiliatePartner);

    $service->cancel($request);

    $request->refresh();
    expect($request->status)->toBe('cancelled');
    expect(AffiliateCommission::first()->status)->toBe('closed');
});
