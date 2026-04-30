<?php

use A2ZWeb\Affiliate\Models\AffiliateAdjustment;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Services\PartnerStatistics;
use A2ZWeb\Affiliate\Tests\User;

it('applies admin adjustments at the partner rate and never leaks the reason field', function () {
    $partner = User::create(['name' => 'X', 'email' => 'p@example.com']);
    $referred = User::create(['name' => 'X', 'email' => 'r@example.com']);

    AffiliateCommission::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $referred->id,
        'period_year' => 2026,
        'period_month' => 3,
        'source_amount_cents' => 30000,
        'commission_amount_cents' => 9000,
        'commission_rate_bp' => 3000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);

    AffiliateAdjustment::create([
        'partner_user_id' => $partner->id,
        'period_year' => 2026,
        'period_month' => 3,
        'type' => AffiliateAdjustment::TYPE_SUBTRACTION,
        'amount_cents' => 4000,
        'currency' => 'usd',
        'reason' => 'penalty',
        'admin_user_id' => 1,
    ]);

    $stats = app(PartnerStatistics::class)->for($partner->id);

    // 9000 commission + (-4000 base × 3000bp / 10000) = 9000 - 1200 = 7800
    expect($stats['total_earned_cents'])->toBe(7800);
    expect($stats['available_to_request_cents'])->toBe(7800);

    expect($stats['adjustments'])->toHaveCount(1);
    expect($stats['adjustments'][0]['base_cents'])->toBe(-4000);
    expect($stats['adjustments'][0]['commission_cents'])->toBe(-1200);

    $serialised = json_encode($stats);
    expect($serialised)->not->toContain('penalty');
    expect($serialised)->not->toContain('admin_user_id');
});
