<?php

namespace A2ZWeb\Affiliate\Notifications;

use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutRequestRejected extends Notification
{
    use Queueable;

    public function __construct(public AffiliatePayoutRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = (new MailMessage)
            ->subject(__('Your payout request was not approved'))
            ->greeting(__('Hello,'))
            ->line(__('Your payout request was not approved.'));

        if (filled($this->request->rejection_reason)) {
            $msg->line(__('Reason: :reason', ['reason' => $this->request->rejection_reason]));
        }

        return $msg->line(__('Your earnings remain available. You can submit a new payout request from the affiliate dashboard.'));
    }
}
