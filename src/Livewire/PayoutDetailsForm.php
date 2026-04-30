<?php

namespace A2ZWeb\Affiliate\Livewire;

use Livewire\Component;

class PayoutDetailsForm extends Component
{
    public string $payout_method = 'paypal';

    public string $paypal_email = '';

    public string $bank_account_holder = '';

    public string $bank_iban = '';

    public string $bank_swift = '';

    public string $bank_address = '';

    public bool $is_company = false;

    public string $company_name = '';

    public string $tax_id = '';

    public function mount(): void
    {
        $partner = auth()->user()->affiliatePartner;
        if (! $partner) {
            return;
        }

        $this->payout_method = $partner->payout_method ?? 'paypal';
        $this->paypal_email = (string) $partner->paypal_email;
        $this->bank_account_holder = (string) $partner->bank_account_holder;
        $this->bank_iban = (string) $partner->bank_iban;
        $this->bank_swift = (string) $partner->bank_swift;
        $this->bank_address = (string) $partner->bank_address;
        $this->is_company = (bool) $partner->is_company;
        $this->company_name = (string) $partner->company_name;
        $this->tax_id = (string) $partner->tax_id;
    }

    public function save(): void
    {
        $partner = auth()->user()->affiliatePartner;
        if (! $partner) {
            return;
        }

        $this->validate([
            'payout_method' => 'required|in:paypal,bank_transfer',
            'paypal_email' => 'nullable|required_if:payout_method,paypal|email',
            'bank_account_holder' => 'nullable|required_if:payout_method,bank_transfer|string|max:191',
            'bank_iban' => 'nullable|required_if:payout_method,bank_transfer|string|max:64',
        ]);

        $partner->update([
            'payout_method' => $this->payout_method,
            'paypal_email' => $this->paypal_email ?: null,
            'bank_account_holder' => $this->bank_account_holder ?: null,
            'bank_iban' => $this->bank_iban ?: null,
            'bank_swift' => $this->bank_swift ?: null,
            'bank_address' => $this->bank_address ?: null,
            'is_company' => $this->is_company,
            'company_name' => $this->company_name ?: null,
            'tax_id' => $this->tax_id ?: null,
        ]);

        session()->flash('status', __('Payout details saved.'));
    }

    public function render()
    {
        return view('affiliate-livewire::payout-details-form');
    }
}
