<?php

namespace A2ZWeb\Affiliate\Notifications;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationApproved extends Notification
{
    use Queueable;

    public function __construct(public AffiliatePartner $partner) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Welcome to the Affiliate Program 🎉'))
            ->greeting(__('You are in!'))
            ->line(__('Your affiliate application has been approved.'))
            ->line(__('Your unique affiliate code: :code', ['code' => $this->partner->code]))
            ->action(__('Open Affiliate Dashboard'), url(config('affiliate.routes.prefix', 'dashboard/affiliate')))
            ->line(__('You can now share your link and start earning commission on every closed month.'));
    }
}
