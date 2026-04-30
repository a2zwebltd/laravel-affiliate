<?php

namespace A2ZWeb\Affiliate\Notifications;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use A2ZWeb\Affiliate\Support\AdminUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommissionStatementIssuedAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AffiliateCommissionStatement $statement) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->statement->commission_amount, 2);
        $cur = strtoupper($this->statement->currency);

        $message = (new MailMessage)
            ->subject(__('[Affiliate Statement] :number — :amount :currency', [
                'number' => $this->statement->statement_number,
                'amount' => $amount,
                'currency' => $cur,
            ]))
            ->greeting(__('Statement issued.'))
            ->line(__('Number: :number', ['number' => $this->statement->statement_number]))
            ->line(__('Partner user ID: :id', ['id' => $this->statement->partner_user_id]))
            ->line(__('Period: :start → :end', [
                'start' => $this->statement->period_start->toDateString(),
                'end' => $this->statement->period_end->toDateString(),
            ]))
            ->line(__('Total: :amount :currency', ['amount' => $amount, 'currency' => $cur]));

        $url = AdminUrl::statement((int) $this->statement->id);
        if ($url !== null) {
            $message->action(__('Open in admin panel'), $url);
        }

        return $message;
    }
}
