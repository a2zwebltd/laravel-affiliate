<?php

namespace A2ZWeb\Affiliate\Http\Controllers;

use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use A2ZWeb\Affiliate\Notifications\PayoutRequestSubmittedAdmin;
use A2ZWeb\Affiliate\Services\PayoutRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;

class PayoutRequestController extends Controller
{
    public function store(Request $request, PayoutRequestService $service): RedirectResponse
    {
        $partner = $request->user()->affiliatePartner;
        abort_unless($partner !== null, 404);

        $request->validate([
            'invoice' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'purchase_order_id' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $payoutRequest = $service->create(
                $partner,
                $request->file('invoice'),
                $request->input('purchase_order_id') ?: null,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payout_request' => $e->getMessage()]);
        }

        $adminEmail = config('affiliate.admin_notification_email');
        if (filled($adminEmail)) {
            Notification::route('mail', $adminEmail)->notify(new PayoutRequestSubmittedAdmin($payoutRequest));
        }

        return back()->with('status', 'Payout request submitted. You will receive an email when it is reviewed.');
    }

    public function cancel(Request $request, AffiliatePayoutRequest $payoutRequest, PayoutRequestService $service): RedirectResponse
    {
        abort_unless($payoutRequest->partner_user_id === $request->user()->getKey(), 403);

        try {
            $service->cancel($payoutRequest);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payout_request' => $e->getMessage()]);
        }

        return back()->with('status', 'Payout request cancelled.');
    }
}
