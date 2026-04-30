<?php

namespace A2ZWeb\Affiliate\Notifications;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationRejected extends Notification
{
    use Queueable;

    public function __construct(public AffiliatePartner $partner) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = (new MailMessage)
            ->subject(__('Update on your affiliate application'))
            ->greeting(__('Thank you for applying.'))
            ->line(__('Unfortunately your affiliate application was not approved at this time.'));

        if (filled($this->partner->rejection_reason)) {
            $msg->line(__('Reason: :reason', ['reason' => $this->partner->rejection_reason]));
        }

        return $msg->line(__('You may re-apply later as your account grows.'));
    }
}
