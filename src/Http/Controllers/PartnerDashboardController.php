<?php

namespace A2ZWeb\Affiliate\Http\Controllers;

use A2ZWeb\Affiliate\Services\EligibilityChecker;
use A2ZWeb\Affiliate\Services\PartnerStatistics;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PartnerDashboardController extends Controller
{
    public function index(Request $request, EligibilityChecker $eligibility, PartnerStatistics $statistics)
    {
        $user = $request->user();
        $partner = $user->affiliatePartner;

        $payload = [
            'user' => $user,
            'partner' => $partner,
            'eligible_to_apply' => $eligibility->isEligibleToApply($user),
            'referrals_needed' => $eligibility->referralsNeeded($user),
            'min_referred_users' => (int) config('affiliate.min_referred_users', 2),
            'referred_count' => $eligibility->referredCount($user),
            'general_terms_url' => config('affiliate.terms.general_url'),
            'affiliate_terms_url' => config('affiliate.terms.affiliate_url'),
            'currency' => config('affiliate.currency', 'usd'),
            'revenue_share_pct' => (int) config('affiliate.revenue_share_bp', 3000) / 100,
            'affiliate_link' => $user->affiliateLink(),
            'stats' => $partner?->isApproved() ? $statistics->for((int) $user->getKey()) : null,
            'payout_requests' => $partner
                ? $partner->payoutRequests()
                    ->with(['statements' => fn ($q) => $q->whereIn('payment_status', ['issued', 'paid'])])
                    ->latest('requested_at')
                    ->limit(20)
                    ->get()
                : collect(),
        ];

        return view('affiliate::partner.dashboard', $payload);
    }
}
