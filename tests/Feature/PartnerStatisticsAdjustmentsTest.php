<?php

use A2ZWeb\Affiliate\Models\AffiliateAdjustment;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use A2ZWeb\Affiliate\Services\PartnerStatistics;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;

it('distributes a month adjustment across referrals proportionally to their share of that month gross', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 15, 12, 0, 0));

    $partner = User::create(['name' => 'P', 'email' => 'p@example.com']);
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    $b = User::create(['name' => 'B', 'email' => 'b@example.com']);

    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $a->id,
        'code_used' => 'TEST',
        'attributed_at' => Carbon::create(2026, 1, 1),
    ]);
    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $b->id,
        'code_used' => 'TEST',
        'attributed_at' => Carbon::create(2026, 1, 1),
    ]);

    AffiliateCommission::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $a->id,
        'period_year' => 2026,
        'period_month' => 2,
        'source_amount_cents' => 80000,
        'commission_amount_cents' => 8000,
        'commission_rate_bp' => 1000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);
    AffiliateCommission::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $b->id,
        'period_year' => 2026,
        'period_month' => 2,
        'source_amount_cents' => 120000,
        'commission_amount_cents' => 12000,
        'commission_rate_bp' => 1000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);

    AffiliateAdjustment::create([
        'partner_user_id' => $partner->id,
        'period_year' => 2026,
        'period_month' => 2,
        'type' => AffiliateAdjustment::TYPE_SUBTRACTION,
        'amount_cents' => 5000,
        'commission_rate_bp' => 10000,
        'currency' => 'usd',
        'reason' => 'chargeback',
        'admin_user_id' => 1,
    ]);

    $stats = app(PartnerStatistics::class)->for($partner->id);

    $rows = collect($stats['referrals'])->keyBy('user_id');

    expect($rows[$a->id]['gross_last_12mo_cents'])->toBe(6000);
    expect($rows[$b->id]['gross_last_12mo_cents'])->toBe(9000);
    expect($stats['total_earned_cents'])->toBe(15000);

    $sumPerReferral = (int) collect($stats['referrals'])->sum('gross_last_12mo_cents');
    expect($sumPerReferral)->toBe($stats['total_earned_cents']);

    Carbon::setTestNow();
});

it('leaves per-referral totals unchanged when a month has only an adjustment and no commissions', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 15, 12, 0, 0));

    $partner = User::create(['name' => 'P', 'email' => 'p2@example.com']);
    $a = User::create(['name' => 'A', 'email' => 'a2@example.com']);

    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $a->id,
        'code_used' => 'TEST',
        'attributed_at' => Carbon::create(2026, 1, 1),
    ]);

    AffiliateCommission::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $a->id,
        'period_year' => 2026,
        'period_month' => 3,
        'source_amount_cents' => 50000,
        'commission_amount_cents' => 5000,
        'commission_rate_bp' => 1000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);

    AffiliateAdjustment::create([
        'partner_user_id' => $partner->id,
        'period_year' => 2026,
        'period_month' => 2,
        'type' => AffiliateAdjustment::TYPE_SUBTRACTION,
        'amount_cents' => 2000,
        'commission_rate_bp' => 5000,
        'currency' => 'usd',
        'reason' => 'no commissions to attribute',
        'admin_user_id' => 1,
    ]);

    $stats = app(PartnerStatistics::class)->for($partner->id);

    $rows = collect($stats['referrals'])->keyBy('user_id');

    // A's per-referral total reflects only the Mar commission — the Feb adjustment
    // has no Feb commissions to attribute against, so it stays at the partner level.
    expect($rows[$a->id]['gross_last_12mo_cents'])->toBe(5000);

    // total_earned_cents reflects both: 5000 commission + (-1000 adjustment impact).
    expect($stats['total_earned_cents'])->toBe(4000);

    Carbon::setTestNow();
});
