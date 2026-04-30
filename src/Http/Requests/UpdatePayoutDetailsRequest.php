<?php

namespace A2ZWeb\Affiliate\Http\Requests;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayoutDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->affiliatePartner !== null;
    }

    public function rules(): array
    {
        return [
            'payout_method' => ['required', Rule::in([AffiliatePartner::PAYOUT_PAYPAL, AffiliatePartner::PAYOUT_BANK])],

            'paypal_email' => ['nullable', 'required_if:payout_method,paypal', 'email:rfc'],
            'bank_account_holder' => ['nullable', 'required_if:payout_method,bank_transfer', 'string', 'max:191'],
            'bank_iban' => ['nullable', 'required_if:payout_method,bank_transfer', 'string', 'max:64'],
            'bank_swift' => ['nullable', 'string', 'max:32'],
            'bank_address' => ['nullable', 'string', 'max:255'],

            'is_company' => ['sometimes', 'boolean'],
            'company_name' => ['nullable', 'required_if:is_company,1', 'string', 'max:191'],
            'tax_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
