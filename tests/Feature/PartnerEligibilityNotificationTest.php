<?php

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use A2ZWeb\Affiliate\Notifications\PartnerEligibleToApply;
use A2ZWeb\Affiliate\Services\ReferralAttributor;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    config()->set('affiliate.min_referred_users', 2);
});

function makeRequestWithCode(string $code): Request
{
    return Request::create('/', 'GET', [], [config('affiliate.cookie_name', 'sa_aff') => $code]);
}

it('emails the partner once when a fresh referral brings them to the eligibility threshold', function () {
    $partner = User::create(['name' => 'P', 'email' => 'p@example.com']);
    AffiliatePartner::create([
        'user_id' => $partner->id,
        'code' => 'PCODE',
        'status' => AffiliatePartner::STATUS_PENDING, // share-only stub, applied_at null
    ]);

    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => User::create(['name' => 'R1', 'email' => 'r1@example.com'])->id,
        'code_used' => 'PCODE',
        'attributed_at' => Carbon::now(),
    ]);

    $newSignup = User::create(['name' => 'R2', 'email' => 'r2@example.com']);
    app(ReferralAttributor::class)->attributeNewUser($newSignup, makeRequestWithCode('PCODE'));

    Notification::assertSentTo(
        $partner,
        PartnerEligibleToApply::class,
        fn () => true,
    );
    Notification::assertSentToTimes($partner, PartnerEligibleToApply::class, 1);
});

it('does not re-email when an additional referral arrives past the threshold', function () {
    $partner = User::create(['name' => 'P', 'email' => 'p2@example.com']);
    AffiliatePartner::create([
        'user_id' => $partner->id,
        'code' => 'PCODE2',
        'status' => AffiliatePartner::STATUS_PENDING,
    ]);

    foreach (['r3@example.com', 'r4@example.com'] as $email) {
        AffiliateReferral::create([
            'partner_user_id' => $partner->id,
            'referred_user_id' => User::create(['email' => $email])->id,
            'code_used' => 'PCODE2',
            'attributed_at' => Carbon::now(),
        ]);
    }

    $newSignup = User::create(['name' => 'R5', 'email' => 'r5@example.com']);
    app(ReferralAttributor::class)->attributeNewUser($newSignup, makeRequestWithCode('PCODE2'));

    Notification::assertNothingSent();
});

it('does not email when the partner already has an open application', function () {
    $partner = User::create(['name' => 'P', 'email' => 'p3@example.com']);
    AffiliatePartner::create([
        'user_id' => $partner->id,
        'code' => 'PCODE3',
        'status' => AffiliatePartner::STATUS_PENDING,
        'applied_at' => Carbon::now(), // open application — blocks eligibility
    ]);

    AffiliateReferral::create([
        'partner_user_id' => $partner->id,
        'referred_user_id' => User::create(['email' => 'r6@example.com'])->id,
        'code_used' => 'PCODE3',
        'attributed_at' => Carbon::now(),
    ]);

    $newSignup = User::create(['email' => 'r7@example.com']);
    app(ReferralAttributor::class)->attributeNewUser($newSignup, makeRequestWithCode('PCODE3'));

    Notification::assertNothingSent();
});

it('emails the new partner exactly once when a last-touch swap puts them at the threshold', function () {
    config()->set('affiliate.attribution', 'last_touch');
    config()->set('affiliate.min_referred_users', 1);

    $p = User::create(['email' => 'p4@example.com']);
    $q = User::create(['email' => 'q4@example.com']);

    AffiliatePartner::create(['user_id' => $p->id, 'code' => 'PP', 'status' => AffiliatePartner::STATUS_PENDING]);
    AffiliatePartner::create(['user_id' => $q->id, 'code' => 'QQ', 'status' => AffiliatePartner::STATUS_PENDING]);

    $referredUser = User::create(['email' => 'u@example.com']);
    AffiliateReferral::create([
        'partner_user_id' => $p->id,
        'referred_user_id' => $referredUser->id,
        'code_used' => 'PP',
        'attributed_at' => Carbon::now(),
    ]);

    app(ReferralAttributor::class)->attributeNewUser($referredUser, makeRequestWithCode('QQ'));

    Notification::assertSentToTimes($q, PartnerEligibleToApply::class, 1);
    Notification::assertNotSentTo($p, PartnerEligibleToApply::class);
});
