<?php

namespace A2ZWeb\Affiliate\Notifications;

use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutRequestApproved extends Notification
{
    use Queueable;

    public function __construct(public AffiliatePayoutRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your payout request was approved'))
            ->greeting(__('Good news!'))
            ->line(__('Your payout request for :amount :currency has been approved.', [
                'amount' => number_format($this->request->net_amount_cents / 100, 2),
                'currency' => strtoupper($this->request->currency),
            ]))
            ->line(__('We will process the payment shortly and send you a confirmation with the transaction reference.'));
    }
}
