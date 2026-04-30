<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;

class ReferralAttributor
{
    public function captureFromRequest(Request $request): void
    {
        $code = $request->query(config('affiliate.query_param', 'aff'));
        if (! is_string($code) || $code === '') {
            return;
        }

        $partner = AffiliatePartner::query()
            ->where('code', $code)
            ->where('status', AffiliatePartner::STATUS_APPROVED)
            ->first();

        if (! $partner) {
            return;
        }

        Cookie::queue(
            config('affiliate.cookie_name', 'sa_aff'),
            $code,
            (int) config('affiliate.cookie_ttl_days', 60) * 24 * 60
        );
    }

    public function attributeNewUser(Model $newUser, Request $request): ?AffiliateReferral
    {
        $code = $request->cookie(config('affiliate.cookie_name', 'sa_aff'))
            ?? $request->query(config('affiliate.query_param', 'aff'));

        if (! is_string($code) || $code === '') {
            return null;
        }

        $partner = AffiliatePartner::query()
            ->where('code', $code)
            ->where('status', AffiliatePartner::STATUS_APPROVED)
            ->first();

        if (! $partner) {
            return null;
        }

        if (! config('affiliate.allow_self_referral', false) && $partner->user_id === $newUser->getKey()) {
            return null;
        }

        $existing = AffiliateReferral::query()->where('referred_user_id', $newUser->getKey())->first();
        if ($existing) {
            if (config('affiliate.attribution', 'first_touch') === 'last_touch') {
                $existing->update([
                    'partner_user_id' => $partner->user_id,
                    'code_used' => $code,
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 512),
                    'attributed_at' => Carbon::now(),
                ]);
            }

            return $existing;
        }

        return AffiliateReferral::create([
            'partner_user_id' => $partner->user_id,
            'referred_user_id' => $newUser->getKey(),
            'code_used' => $code,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'attributed_at' => Carbon::now(),
        ]);
    }

    /**
     * Manually attach a referral, bypassing cookie/code lookup.
     *
     * Used by admin tools to backfill historical attributions or correct missing referrals.
     * Throws DomainException when the target user is already referred by a different partner
     * or self-referral is disallowed.
     */
    public function manuallyAttach(
        AffiliatePartner $partner,
        Model $referredUser,
        ?Carbon $attributedAt = null,
        ?string $codeUsed = null,
    ): AffiliateReferral {
        if (! config('affiliate.allow_self_referral', false) && (int) $partner->user_id === (int) $referredUser->getKey()) {
            throw new \DomainException('Self-referral is not allowed.');
        }

        $existing = AffiliateReferral::query()->where('referred_user_id', $referredUser->getKey())->first();

        if ($existing && (int) $existing->partner_user_id !== (int) $partner->user_id) {
            throw new \DomainException(sprintf(
                'User #%d is already referred by partner #%d.',
                $referredUser->getKey(),
                $existing->partner_user_id,
            ));
        }

        if ($existing) {
            $existing->update([
                'attributed_at' => $attributedAt ?? $existing->attributed_at ?? Carbon::now(),
                'code_used' => $codeUsed ?? $existing->code_used,
            ]);

            return $existing;
        }

        return AffiliateReferral::create([
            'partner_user_id' => $partner->user_id,
            'referred_user_id' => $referredUser->getKey(),
            'code_used' => $codeUsed ?? (string) $partner->code,
            'ip' => null,
            'user_agent' => 'manual-admin-attach',
            'attributed_at' => $attributedAt ?? Carbon::now(),
        ]);
    }
}
