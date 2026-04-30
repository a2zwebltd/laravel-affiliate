<?php

namespace A2ZWeb\Affiliate\Livewire;

use A2ZWeb\Affiliate\Notifications\PayoutRequestSubmittedAdmin;
use A2ZWeb\Affiliate\Services\PayoutRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class PayoutRequestForm extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $invoice = null;

    public string $purchase_order_id = '';

    public function submit(PayoutRequestService $service): void
    {
        $partner = auth()->user()->affiliatePartner;
        if (! $partner) {
            return;
        }

        $this->validate([
            'invoice' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'purchase_order_id' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $request = $service->create(
                $partner,
                $this->invoice ? new UploadedFile($this->invoice->getRealPath(), $this->invoice->getClientOriginalName()) : null,
                $this->purchase_order_id ?: null,
            );
        } catch (\RuntimeException $e) {
            $this->addError('payout', $e->getMessage());

            return;
        }

        $adminEmail = config('affiliate.admin_notification_email');
        if (filled($adminEmail)) {
            Notification::route('mail', $adminEmail)->notify(new PayoutRequestSubmittedAdmin($request));
        }

        session()->flash('status', __('Payout request submitted.'));
        $this->dispatch('payout-requested');
    }

    public function render()
    {
        return view('affiliate-livewire::payout-request-form');
    }
}
