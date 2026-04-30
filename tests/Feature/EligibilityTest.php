<?php

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use A2ZWeb\Affiliate\Services\EligibilityChecker;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;

it('requires the configured minimum number of referred users', function () {
    config()->set('affiliate.min_referred_users', 2);

    $partner = User::create(['name' => 'P', 'email' => 'p@example.com']);

    $checker = app(EligibilityChecker::class);
    expect($checker->isEligibleToApply($partner))->toBeFalse();

    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => User::create(['name' => 'R1', 'email' => 'r1@example.com'])->id,
        'code_used' => 'X',
        'attributed_at' => Carbon::now(),
    ]);
    expect($checker->referralsNeeded($partner))->toBe(1);

    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => User::create(['name' => 'R2', 'email' => 'r2@example.com'])->id,
        'code_used' => 'X',
        'attributed_at' => Carbon::now(),
    ]);
    expect($checker->isEligibleToApply($partner))->toBeTrue();
});

it('blocks re-application after a real submitted application is pending', function () {
    $partner = User::create(['name' => 'P', 'email' => 'p@example.com']);
    AffiliatePartner::create([
        'user_id' => $partner->id,
        'code' => 'ABC',
        'status' => AffiliatePartner::STATUS_PENDING,
        'applied_at' => Carbon::now(),
    ]);

    expect(app(EligibilityChecker::class)->isEligibleToApply($partner))->toBeFalse();
});

it('does not block application when only a share-code stub exists', function () {
    config()->set('affiliate.min_referred_users', 0);
    $partner = User::create(['name' => 'Stub', 'email' => 'stub@example.com']);
    // stub row created lazily by HasAffiliateProgram; applied_at IS NULL
    AffiliatePartner::create([
        'user_id' => $partner->id,
        'code' => 'STUB',
        'status' => AffiliatePartner::STATUS_PENDING,
    ]);

    $checker = app(EligibilityChecker::class);
    expect($checker->hasOpenApplication($partner))->toBeFalse();
    expect($checker->isEligibleToApply($partner))->toBeTrue();
});
