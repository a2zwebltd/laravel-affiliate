<?php

use A2ZWeb\Affiliate\Contracts\RevenueResolver;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use A2ZWeb\Affiliate\Services\MonthlyCloser;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-04-15 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

class FixedRevenueResolver implements RevenueResolver
{
    public function __construct(public int $cents) {}

    public function revenueForUserInMonth(int $userId, int $year, int $month): int
    {
        return $this->cents;
    }

    public function currentMonthRunningRevenueCents(int $userId): int
    {
        return 0;
    }
}

it('closes a past month and creates commissions', function () {
    $partner = User::create(['name' => 'X', 'email' => 'p@example.com']);
    $referredA = User::create(['name' => 'X', 'email' => 'a@example.com']);
    $referredB = User::create(['name' => 'X', 'email' => 'b@example.com']);

    AffiliatePartner::create([
        'user_id' => $partner->id,
        'code' => 'CODE',
        'status' => AffiliatePartner::STATUS_APPROVED,
        'program_joined_at' => '2026-01-01',
    ]);

    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $referredA->id,
        'code_used' => 'CODE',
        'attributed_at' => '2026-02-01 10:00:00',
    ]);
    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $referredB->id,
        'code_used' => 'CODE',
        'attributed_at' => '2026-02-01 10:00:00',
    ]);

    app()->bind(RevenueResolver::class, fn () => new FixedRevenueResolver(10000));
    config()->set('affiliate.revenue_share_bp', 3000);

    $closer = app(MonthlyCloser::class);
    $touched = $closer->closeMonth(2026, 3);

    expect($touched)->toBe(2);
    expect(AffiliateCommission::count())->toBe(2);
    expect(AffiliateCommission::sum('commission_amount_cents'))->toBe(6000);
});

it('refuses to close current or future month', function () {
    app()->bind(RevenueResolver::class, fn () => new FixedRevenueResolver(0));
    expect(fn () => app(MonthlyCloser::class)->closeMonth(2026, 4))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(MonthlyCloser::class)->closeMonth(2026, 5))->toThrow(InvalidArgumentException::class);
});

it('skips months before program_joined_at', function () {
    $partner = User::create(['name' => 'X', 'email' => 'p@example.com']);
    $referred = User::create(['name' => 'X', 'email' => 'a@example.com']);

    AffiliatePartner::create([
        'user_id' => $partner->id,
        'code' => 'CODE',
        'status' => AffiliatePartner::STATUS_APPROVED,
        'program_joined_at' => '2026-04-01',
    ]);
    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $referred->id,
        'code_used' => 'CODE',
        'attributed_at' => '2026-01-01',
    ]);

    app()->bind(RevenueResolver::class, fn () => new FixedRevenueResolver(10000));

    expect(app(MonthlyCloser::class)->closeMonth(2026, 3))->toBe(0);
});

it('uses per-partner revenue_share_bp override when set', function () {
    $partnerA = User::create(['name' => 'X', 'email' => 'a@example.com']);
    $partnerB = User::create(['name' => 'X', 'email' => 'b@example.com']);
    $refA = User::create(['name' => 'X', 'email' => 'refa@example.com']);
    $refB = User::create(['name' => 'X', 'email' => 'refb@example.com']);

    AffiliatePartner::create([
        'user_id' => $partnerA->id,
        'code' => 'A',
        'status' => AffiliatePartner::STATUS_APPROVED,
        'program_joined_at' => '2026-01-01',
        'revenue_share_bp' => 4000, // VIP — 40%
    ]);
    AffiliatePartner::create([
        'user_id' => $partnerB->id,
        'code' => 'B',
        'status' => AffiliatePartner::STATUS_APPROVED,
        'program_joined_at' => '2026-01-01',
        // revenue_share_bp = null → falls back to global config
    ]);
    AffiliateReferral::create([
        'partner_user_id' => $partnerA->id,
        'referred_user_id' => $refA->id,
        'code_used' => 'A',
        'attributed_at' => '2026-02-01',
    ]);
    AffiliateReferral::create([
        'partner_user_id' => $partnerB->id,
        'referred_user_id' => $refB->id,
        'code_used' => 'B',
        'attributed_at' => '2026-02-01',
    ]);

    app()->bind(RevenueResolver::class, fn () => new FixedRevenueResolver(10000));
    config()->set('affiliate.revenue_share_bp', 3000);

    app(MonthlyCloser::class)->closeMonth(2026, 3);

    $a = AffiliateCommission::where('partner_user_id', $partnerA->id)->first();
    $b = AffiliateCommission::where('partner_user_id', $partnerB->id)->first();
    expect($a->commission_amount_cents)->toBe(4000); // 40% of 10000
    expect($a->commission_rate_bp)->toBe(4000);
    expect($b->commission_amount_cents)->toBe(3000); // 30% of 10000
    expect($b->commission_rate_bp)->toBe(3000);
});

it('is idempotent — re-running does not duplicate', function () {
    $partner = User::create(['name' => 'X', 'email' => 'p@example.com']);
    $referred = User::create(['name' => 'X', 'email' => 'a@example.com']);

    AffiliatePartner::create([
        'user_id' => $partner->id,
        'code' => 'CODE',
        'status' => AffiliatePartner::STATUS_APPROVED,
        'program_joined_at' => '2026-01-01',
    ]);
    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $referred->id,
        'code_used' => 'CODE',
        'attributed_at' => '2026-01-01',
    ]);

    app()->bind(RevenueResolver::class, fn () => new FixedRevenueResolver(10000));

    app(MonthlyCloser::class)->closeMonth(2026, 3);
    app(MonthlyCloser::class)->closeMonth(2026, 3);

    expect(AffiliateCommission::count())->toBe(1);
});
