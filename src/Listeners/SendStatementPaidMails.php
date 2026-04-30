<?php

namespace A2ZWeb\Affiliate\Listeners;

use A2ZWeb\Affiliate\Events\StatementPaid;
use A2ZWeb\Affiliate\Notifications\CommissionStatementPaid;

class SendStatementPaidMails
{
    public function handle(StatementPaid $event): void
    {
        $partner = $event->statement->partner;
        if ($partner) {
            $partner->notify(new CommissionStatementPaid($event->statement));
        }
    }
}
