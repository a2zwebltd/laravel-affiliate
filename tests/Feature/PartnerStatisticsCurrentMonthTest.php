<?php

use A2ZWeb\Affiliate\Contracts\RevenueResolver;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use A2ZWeb\Affiliate\Services\PartnerStatistics;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;

class StubRunningRevenueResolver implements RevenueResolver
{
    /**
     * @param  array<int, int>  $perUser  user_id => current-month-running source cents
     */
    public function __construct(public array $perUser) {}

    public function revenueForUserInMonth(int $userId, int $year, int $month): int
    {
        return 0;
    }

    public function currentMonthRunningRevenueCents(int $userId): int
    {
        return $this->perUser[$userId] ?? 0;
    }
}

afterEach(function () {
    Carbon::setTestNow();
});

it('exposes current-month running commission and a flagged in-progress chart bar', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 15, 12, 0, 0));

    $partner = User::create(['name' => 'P', 'email' => 'curr-p@example.com']);
    $a = User::create(['name' => 'A', 'email' => 'curr-a@example.com']);
    $b = User::create(['name' => 'B', 'email' => 'curr-b@example.com']);

    AffiliatePartner::create([
        'user_id' => $partner->id,
        'status' => 'approved',
        'code' => 'CURR',
        'revenue_share_bp' => 2000,
    ]);

    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $a->id,
        'code_used' => 'CURR',
        'attributed_at' => Carbon::create(2026, 1, 1),
    ]);
    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $b->id,
        'code_used' => 'CURR',
        'attributed_at' => Carbon::create(2026, 1, 1),
    ]);

    app()->instance(RevenueResolver::class, new StubRunningRevenueResolver([
        $a->id => 50000,  // $500 source revenue this month
        $b->id => 30000,  // $300 source revenue this month
    ]));

    $stats = app(PartnerStatistics::class)->for($partner->id);

    // 80000 source × 2000bp / 10000 = 16000 commission cents
    expect($stats['current_month_running_cents'])->toBe(16000);

    // total_earned excludes the in-progress month
    expect($stats['total_earned_cents'])->toBe(0);

    $currentBar = collect($stats['monthly'])->firstWhere('is_current', true);
    expect($currentBar)->not->toBeNull();
    expect($currentBar['year'])->toBe(2026);
    expect($currentBar['month'])->toBe(4);
    expect($currentBar['gross_cents'])->toBe(16000);

    // Closed months remain flagged is_current=false
    $closedBars = collect($stats['monthly'])->where('is_current', false);
    expect($closedBars)->toHaveCount(12);
    expect($closedBars->sum('gross_cents'))->toBe(0);
});

it('returns zero current-month running commission when no resolver data', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 15, 12, 0, 0));

    $partner = User::create(['name' => 'P', 'email' => 'curr2-p@example.com']);
    $a = User::create(['name' => 'A', 'email' => 'curr2-a@example.com']);

    AffiliatePartner::create([
        'user_id' => $partner->id,
        'status' => 'approved',
        'code' => 'CURR2',
        'revenue_share_bp' => 3000,
    ]);

    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => $a->id,
        'code_used' => 'CURR2',
        'attributed_at' => Carbon::create(2026, 1, 1),
    ]);

    // Default NullRevenueResolver returns 0.
    $stats = app(PartnerStatistics::class)->for($partner->id);

    expect($stats['current_month_running_cents'])->toBe(0);

    $currentBar = collect($stats['monthly'])->firstWhere('is_current', true);
    expect($currentBar['gross_cents'])->toBe(0);
});
