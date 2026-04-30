<?php

namespace A2ZWeb\Affiliate\Http\Controllers;

use A2ZWeb\Affiliate\Events\ApplicationSubmitted;
use A2ZWeb\Affiliate\Http\Requests\ApplyRequest;
use A2ZWeb\Affiliate\Http\Requests\UpdatePayoutDetailsRequest;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateTermsAcceptance;
use A2ZWeb\Affiliate\Notifications\ApplicationSubmittedAdmin;
use A2ZWeb\Affiliate\Services\AffiliateCodeGenerator;
use A2ZWeb\Affiliate\Services\EligibilityChecker;
use A2ZWeb\Affiliate\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class ApplyController extends Controller
{
    public function show(Request $request, EligibilityChecker $eligibility)
    {
        $user = $request->user();
        $defaults = method_exists($user, 'affiliateApplyDefaults') ? $user->affiliateApplyDefaults() : [];

        return view('affiliate::partner.apply', [
            'user' => $user,
            'eligible' => $eligibility->isEligibleToApply($user),
            'general_terms_url' => config('affiliate.terms.general_url'),
            'affiliate_terms_url' => config('affiliate.terms.affiliate_url'),
            'defaults' => $defaults,
            'countries' => Countries::list(),
        ]);
    }

    public function store(ApplyRequest $request, EligibilityChecker $eligibility, AffiliateCodeGenerator $codes): RedirectResponse
    {
        $user = $request->user();

        abort_unless($eligibility->isEligibleToApply($user), 403, 'You are not eligible to apply yet.');

        $partner = DB::transaction(function () use ($request, $user, $codes): AffiliatePartner {
            $partner = AffiliatePartner::query()->where('user_id', $user->getKey())->first()
                ?? new AffiliatePartner(['user_id' => $user->getKey()]);

            if (! $partner->code) {
                $partner->code = $codes->generate((int) $user->getKey());
            }

            $partner->fill([
                'status' => AffiliatePartner::STATUS_PENDING,
                'billing_full_name' => $request->input('billing_full_name'),
                'billing_address' => $request->input('billing_address'),
                'country_of_tax_residence' => strtoupper((string) $request->input('country_of_tax_residence')),
                'contact_email' => $request->input('contact_email'),
                'contact_phone' => $request->input('contact_phone'),
                'payout_method' => $request->string('payout_method'),
                'paypal_email' => $request->input('paypal_email'),
                'bank_account_holder' => $request->input('bank_account_holder'),
                'bank_iban' => $request->input('bank_iban'),
                'bank_swift' => $request->input('bank_swift'),
                'bank_address' => $request->input('bank_address'),
                'is_company' => (bool) $request->boolean('is_company'),
                'company_name' => $request->input('company_name'),
                'tax_id' => $request->input('tax_id'),
                'applied_at' => Carbon::now(),
                'accepted_general_terms_version' => config('affiliate.terms.general_version'),
                'accepted_affiliate_terms_version' => config('affiliate.terms.affiliate_version'),
                'accepted_terms_at' => Carbon::now(),
                'accepted_terms_ip' => $request->ip(),
            ]);
            $partner->save();

            AffiliateTermsAcceptance::create([
                'user_id' => $user->getKey(),
                'affiliate_partner_id' => $partner->id,
                'general_terms_version' => (string) config('affiliate.terms.general_version'),
                'affiliate_terms_version' => (string) config('affiliate.terms.affiliate_version'),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'accepted_at' => Carbon::now(),
            ]);

            return $partner;
        });

        $adminEmail = config('affiliate.admin_notification_email');
        if (filled($adminEmail)) {
            Notification::route('mail', $adminEmail)->notify(new ApplicationSubmittedAdmin($partner));
        }

        Event::dispatch(new ApplicationSubmitted($partner));

        return redirect()
            ->route(config('affiliate.routes.name_prefix', 'affiliate.').'dashboard')
            ->with('status', 'Your application has been submitted. We will email you once reviewed.');
    }

    public function updatePayoutDetails(UpdatePayoutDetailsRequest $request): RedirectResponse
    {
        $partner = $request->user()->affiliatePartner;
        $partner->update($request->validated());

        return back()->with('status', 'Payout details updated.');
    }
}
