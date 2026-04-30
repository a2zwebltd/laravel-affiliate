<?php

namespace A2ZWeb\Affiliate\Listeners;

use A2ZWeb\Affiliate\Events\StatementIssued;
use A2ZWeb\Affiliate\Notifications\CommissionStatementIssued;
use A2ZWeb\Affiliate\Notifications\CommissionStatementIssuedAdminNotification;
use Illuminate\Support\Facades\Notification;

class SendStatementIssuedMails
{
    public function handle(StatementIssued $event): void
    {
        $statement = $event->statement;

        $partner = $statement->partner;
        if ($partner) {
            $partner->notify(new CommissionStatementIssued($statement));
            $statement->update(['sent_to_affiliate_at' => now()]);
        }

        $adminEmail = config('affiliate_statements.admin_notification_email')
            ?: config('affiliate.admin_notification_email');
        if (filled($adminEmail)) {
            Notification::route('mail', $adminEmail)
                ->notify(new CommissionStatementIssuedAdminNotification($statement));
        }
    }
}
