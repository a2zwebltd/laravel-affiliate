<?php

namespace A2ZWeb\Affiliate\Livewire;

use A2ZWeb\Affiliate\Notifications\PayoutRequestSubmittedAdmin;
use A2ZWeb\Affiliate\Services\EligibilityChecker;
use A2ZWeb\Affiliate\Services\PartnerStatistics;
use A2ZWeb\Affiliate\Services\PayoutRequestService;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;

class PartnerDashboard extends Component
{
    public function requestPayout(PayoutRequestService $service): void
    {
        $partner = auth()->user()->affiliatePartner;
        if (! $partner) {
            return;
        }

        try {
            $request = $service->create($partner);
        } catch (\RuntimeException $e) {
            $this->addError('payout', $e->getMessage());

            return;
        }

        $adminEmail = config('affiliate.admin_notification_email');
        if (filled($adminEmail)) {
            Notification::route('mail', $adminEmail)->notify(new PayoutRequestSubmittedAdmin($request));
        }

        session()->flash('status', __('Payout request submitted.'));
    }

    public function render()
    {
        $user = auth()->user();
        $partner = $user->affiliatePartner;
        $eligibility = app(EligibilityChecker::class);

        return view('affiliate-livewire::partner-dashboard', [
            'user' => $user,
            'partner' => $partner,
            'eligible_to_apply' => $eligibility->isEligibleToApply($user),
            'referrals_needed' => $eligibility->referralsNeeded($user),
            'min_referred_users' => (int) config('affiliate.min_referred_users', 2),
            'referred_count' => $eligibility->referredCount($user),
            'currency' => config('affiliate.currency', 'usd'),
            'revenue_share_pct' => ($partner?->effectiveRevenueShareBp() ?? (int) config('affiliate.revenue_share_bp', 3000)) / 100,
            'affiliate_link' => $user->affiliateLink(),
            'general_terms_url' => config('affiliate.terms.general_url'),
            'affiliate_terms_url' => config('affiliate.terms.affiliate_url'),
            'stats' => $partner?->isApproved() ? app(PartnerStatistics::class)->for((int) $user->getKey()) : null,
            'payout_requests' => $partner
                ? $partner->payoutRequests()->latest('requested_at')->limit(20)->get()
                : collect(),
        ]);
    }
}
