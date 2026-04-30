<?php

namespace A2ZWeb\Affiliate\Notifications;

use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use A2ZWeb\Affiliate\Support\AdminUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutRequestSubmittedAdmin extends Notification
{
    use Queueable;

    public function __construct(public AffiliatePayoutRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('[Affiliate] New payout request: :amount :currency', [
                'amount' => number_format($this->request->net_amount_cents / 100, 2),
                'currency' => strtoupper($this->request->currency),
            ]))
            ->greeting(__('A partner has requested a payout.'))
            ->line(__('Partner user ID: :id', ['id' => $this->request->partner_user_id]))
            ->line(__('Period: :start → :end', [
                'start' => $this->request->period_start->toDateString(),
                'end' => $this->request->period_end->toDateString(),
            ]))
            ->line(__('Gross: :amount', ['amount' => number_format($this->request->gross_amount_cents / 100, 2)]))
            ->line(__('Adjustments: :amount', ['amount' => number_format($this->request->adjustments_amount_cents / 100, 2)]))
            ->line(__('Net: :amount', ['amount' => number_format($this->request->net_amount_cents / 100, 2)]));

        $url = AdminUrl::payoutRequest((int) $this->request->id);
        if ($url !== null) {
            $message->action(__('Review payout in admin panel'), $url);
        } else {
            $message->line(__('Please review in the admin panel.'));
        }

        return $message;
    }
}
