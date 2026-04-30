<?php

namespace A2ZWeb\Affiliate\Notifications;

use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutRequestPaid extends Notification
{
    use Queueable;

    public function __construct(public AffiliatePayoutRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->request->net_amount_cents / 100, 2);
        $currency = strtoupper($this->request->currency);

        return (new MailMessage)
            ->subject(__('Payout sent: :amount :currency', ['amount' => $amount, 'currency' => $currency]))
            ->greeting(__('Payout sent!'))
            ->line(__('We just paid out :amount :currency to your account.', ['amount' => $amount, 'currency' => $currency]))
            ->line(__('Payment reference: :reference', ['reference' => $this->request->payment_reference]))
            ->line(__('Thanks for being part of our affiliate program.'));
    }
}
