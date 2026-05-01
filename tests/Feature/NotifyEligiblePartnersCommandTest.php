<?php

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use A2ZWeb\Affiliate\Notifications\PartnerEligibleToApply;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    config()->set('affiliate.min_referred_users', 2);
});

function seedPartnerWithReferrals(string $email, string $code, int $referrals, ?Carbon $appliedAt = null): User
{
    $partner = User::create(['email' => $email]);
    AffiliatePartner::create([
        'user_id' => $partner->id,
        'code' => $code,
        'status' => AffiliatePartner::STATUS_PENDING,
        'applied_at' => $appliedAt,
    ]);

    for ($i = 0; $i < $referrals; $i++) {
        AffiliateReferral::create([
            'partner_user_id' => $partner->id,
            'referred_user_id' => User::create(['email' => $code.'-r'.$i.'@example.com'])->id,
            'code_used' => $code,
            'attributed_at' => Carbon::now(),
        ]);
    }

    return $partner;
}

it('notifies only currently-eligible partners and skips the rest', function () {
    $a = seedPartnerWithReferrals('a@example.com', 'AAA', 1);                  // below threshold
    $b = seedPartnerWithReferrals('b@example.com', 'BBB', 2);                  // eligible
    $c = seedPartnerWithReferrals('c@example.com', 'CCC', 2, Carbon::now());   // open application — blocks

    $this->artisan('affiliate:notify-eligible', ['--force' => true])
        ->assertSuccessful();

    Notification::assertSentToTimes($b, PartnerEligibleToApply::class, 1);
    Notification::assertNotSentTo($a, PartnerEligibleToApply::class);
    Notification::assertNotSentTo($c, PartnerEligibleToApply::class);
});

it('lists eligible recipients without sending in dry-run', function () {
    $eligible = seedPartnerWithReferrals('dry@example.com', 'DRY', 2);
    seedPartnerWithReferrals('skip@example.com', 'SKIP', 1);

    $this->artisan('affiliate:notify-eligible', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->expectsOutputToContain('dry@example.com')
        ->assertSuccessful();

    Notification::assertNothingSent();
});
