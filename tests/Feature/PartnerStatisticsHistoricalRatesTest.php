<?php

use A2ZWeb\Affiliate\Models\AffiliateAdjustment;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use A2ZWeb\Affiliate\Services\PartnerStatistics;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;

it('aggregates historical earnings using stored rates, ignoring the partner current revenue_share_bp', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 15, 12, 0, 0));

    $partner = User::create(['name' => 'P', 'email' => 'historical-p@example.com']);
    $referred = User::create(['name' => 'A', 'email' => 'historical-a@example.com']);

    $partnerRow = AffiliatePartner::create([
        'user_id' => $partner->id,
        'status' => 'approved',
        'code' => 'HIST',
        'revenue_share_bp' => 1500,
    ]);

    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $referred->id,
        'code_used' => 'HIST',
        'attributed_at' => Carbon::create(2026, 1, 1),
    ]);

    foreach ([
        ['month' => 1, 'commission' => 30000, 'rate' => 3000],
        ['month' => 2, 'commission' => 20000, 'rate' => 2000],
        ['month' => 3, 'commission' => 25000, 'rate' => 2500],
    ] as $r) {
        AffiliateCommission::create([
            'partner_user_id' => $partner->id,
            'referred_user_id' => $referred->id,
            'period_year' => 2026,
            'period_month' => $r['month'],
            'source_amount_cents' => 100000,
            'commission_amount_cents' => $r['commission'],
            'commission_rate_bp' => $r['rate'],
            'currency' => 'usd',
            'status' => AffiliateCommission::STATUS_CLOSED,
        ]);
    }

    AffiliateAdjustment::create([
        'partner_user_id' => $partner->id,
        'period_year' => 2026,
        'period_month' => 2,
        'type' => AffiliateAdjustment::TYPE_SUBTRACTION,
        'amount_cents' => 10000,
        'commission_rate_bp' => 2000,
        'currency' => 'usd',
        'reason' => 'feb correction',
        'admin_user_id' => 1,
        'status' => AffiliateAdjustment::STATUS_CLOSED,
    ]);

    AffiliateAdjustment::create([
        'partner_user_id' => $partner->id,
        'period_year' => 2026,
        'period_month' => 3,
        'type' => AffiliateAdjustment::TYPE_SUBTRACTION,
        'amount_cents' => 4000,
        'commission_rate_bp' => 2500,
        'currency' => 'usd',
        'reason' => 'mar correction',
        'admin_user_id' => 1,
        'status' => AffiliateAdjustment::STATUS_CLOSED,
    ]);

    $stats = app(PartnerStatistics::class)->for($partner->id);

    // Net per-referral: 30000 + (20000 - 2000) + (25000 - 1000) = 72000.
    $rows = collect($stats['referrals'])->keyBy('user_id');
    expect($rows[$referred->id]['gross_last_12mo_cents'])->toBe(72000);
    expect($stats['total_earned_cents'])->toBe(72000);

    // Snapshot the result, then prove the partner's current rate is irrelevant
    // by mutating it to anything else and re-running compute(). Same numbers.
    $service = app(PartnerStatistics::class);

    foreach ([5000, 0, 9999] as $newRate) {
        $partnerRow->update(['revenue_share_bp' => $newRate]);
        $service->forget($partner->id);
        $next = $service->for($partner->id);

        expect($next['referrals'])->toBe($stats['referrals']);
        expect($next['total_earned_cents'])->toBe($stats['total_earned_cents']);
        expect($next['monthly'])->toBe($stats['monthly']);
        expect($next['available_to_request_cents'])->toBe($stats['available_to_request_cents']);
    }

    Carbon::setTestNow();
});
