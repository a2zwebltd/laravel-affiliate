<?php

namespace A2ZWeb\Affiliate\Notifications;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Support\AdminUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedAdmin extends Notification
{
    use Queueable;

    public function __construct(public AffiliatePartner $partner) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('[Affiliate] New affiliate program application'))
            ->greeting(__('A new affiliate application has been submitted.'))
            ->line(__('User ID: :id', ['id' => $this->partner->user_id]))
            ->line(__('Code: :code', ['code' => $this->partner->code]))
            ->line(__('Payout method: :method', ['method' => $this->partner->payout_method]));

        $url = AdminUrl::partner((int) $this->partner->id);
        if ($url !== null) {
            $message->action(__('Review in admin panel'), $url);
        } else {
            $message->line(__('Please review and approve/reject in the admin panel.'));
        }

        return $message;
    }
}
