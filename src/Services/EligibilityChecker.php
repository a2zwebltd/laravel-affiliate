<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use Illuminate\Database\Eloquent\Model;

class EligibilityChecker
{
    public function isEligibleToApply(Model $user): bool
    {
        if ($this->hasOpenApplication($user)) {
            return false;
        }

        return $this->referredCount($user) >= (int) config('affiliate.min_referred_users', 2);
    }

    public function hasOpenApplication(Model $user): bool
    {
        return AffiliatePartner::query()
            ->where('user_id', $user->getKey())
            ->where(function ($q): void {
                $q->where('status', AffiliatePartner::STATUS_APPROVED)
                    ->orWhere('status', AffiliatePartner::STATUS_SUSPENDED)
                    ->orWhere(function ($q2): void {
                        $q2->where('status', AffiliatePartner::STATUS_PENDING)
                            ->whereNotNull('applied_at');
                    });
            })
            ->exists();
    }

    public function referredCount(Model $user): int
    {
        return $user->affiliateReferralsAsPartner()->count();
    }

    public function referralsNeeded(Model $user): int
    {
        $needed = (int) config('affiliate.min_referred_users', 2) - $this->referredCount($user);

        return max(0, $needed);
    }
}
