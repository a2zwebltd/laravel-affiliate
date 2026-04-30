<?php

namespace A2ZWeb\Affiliate\Livewire;

use A2ZWeb\Affiliate\Events\ApplicationSubmitted;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateTermsAcceptance;
use A2ZWeb\Affiliate\Notifications\ApplicationSubmittedAdmin;
use A2ZWeb\Affiliate\Services\AffiliateCodeGenerator;
use A2ZWeb\Affiliate\Services\EligibilityChecker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;

class ApplicationForm extends Component
{
    public string $billing_full_name = '';

    public string $billing_address = '';

    public string $country_of_tax_residence = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    public string $payout_method = 'paypal';

    public string $paypal_email = '';

    public string $bank_account_holder = '';

    public string $bank_iban = '';

    public string $bank_swift = '';

    public string $bank_address = '';

    public bool $is_company = false;

    public string $company_name = '';

    public string $tax_id = '';

    public bool $accept_general_terms = false;

    public bool $accept_affiliate_terms = false;

    protected function rules(): array
    {
        return [
            'accept_general_terms' => 'accepted',
            'accept_affiliate_terms' => 'accepted',
            'billing_full_name' => 'required|string|max:191',
            'billing_address' => 'required|string|max:1000',
            'country_of_tax_residence' => 'required|string|size:2',
            'contact_email' => 'nullable|email|max:191',
            'contact_phone' => 'nullable|string|max:64',
            'payout_method' => 'required|in:paypal,bank_transfer',
            'paypal_email' => 'nullable|required_if:payout_method,paypal|email',
            'bank_account_holder' => 'nullable|required_if:payout_method,bank_transfer|string|max:191',
            'bank_iban' => 'nullable|required_if:payout_method,bank_transfer|string|max:64',
            'bank_swift' => 'nullable|string|max:32',
            'bank_address' => 'nullable|string|max:255',
            'is_company' => 'boolean',
            'company_name' => 'nullable|required_if:is_company,true|string|max:191',
            'tax_id' => 'nullable|string|max:64',
        ];
    }

    public function submit(EligibilityChecker $eligibility, AffiliateCodeGenerator $codes): void
    {
        $user = auth()->user();

        if (! $eligibility->isEligibleToApply($user)) {
            $this->addError('eligibility', __('You are not eligible to apply yet.'));

            return;
        }

        $this->validate();

        $partner = DB::transaction(function () use ($user, $codes): AffiliatePartner {
            $partner = AffiliatePartner::query()->where('user_id', $user->getKey())->first()
                ?? new AffiliatePartner(['user_id' => $user->getKey()]);

            if (! $partner->code) {
                $partner->code = $codes->generate((int) $user->getKey());
            }

            $partner->fill([
                'status' => AffiliatePartner::STATUS_PENDING,
                'billing_full_name' => $this->billing_full_name,
                'billing_address' => $this->billing_address,
                'country_of_tax_residence' => strtoupper($this->country_of_tax_residence),
                'contact_email' => $this->contact_email ?: null,
                'contact_phone' => $this->contact_phone ?: null,
                'payout_method' => $this->payout_method,
                'paypal_email' => $this->paypal_email ?: null,
                'bank_account_holder' => $this->bank_account_holder ?: null,
                'bank_iban' => $this->bank_iban ?: null,
                'bank_swift' => $this->bank_swift ?: null,
                'bank_address' => $this->bank_address ?: null,
                'is_company' => $this->is_company,
                'company_name' => $this->company_name ?: null,
                'tax_id' => $this->tax_id ?: null,
                'applied_at' => Carbon::now(),
                'accepted_general_terms_version' => config('affiliate.terms.general_version'),
                'accepted_affiliate_terms_version' => config('affiliate.terms.affiliate_version'),
                'accepted_terms_at' => Carbon::now(),
                'accepted_terms_ip' => request()->ip(),
            ]);
            $partner->save();

            AffiliateTermsAcceptance::create([
                'user_id' => $user->getKey(),
                'affiliate_partner_id' => $partner->id,
                'general_terms_version' => (string) config('affiliate.terms.general_version'),
                'affiliate_terms_version' => (string) config('affiliate.terms.affiliate_version'),
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 512),
                'accepted_at' => Carbon::now(),
            ]);

            return $partner;
        });

        $adminEmail = config('affiliate.admin_notification_email');
        if (filled($adminEmail)) {
            Notification::route('mail', $adminEmail)->notify(new ApplicationSubmittedAdmin($partner));
        }

        Event::dispatch(new ApplicationSubmitted($partner));

        session()->flash('status', __('Application submitted.'));
        $this->redirect(route(config('affiliate.routes.name_prefix', 'affiliate.').'dashboard'));
    }

    public function render()
    {
        return view('affiliate-livewire::application-form', [
            'general_terms_url' => config('affiliate.terms.general_url'),
            'affiliate_terms_url' => config('affiliate.terms.affiliate_url'),
        ]);
    }
}
