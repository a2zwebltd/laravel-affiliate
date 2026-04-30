<?php

namespace A2ZWeb\Affiliate\Console\Commands;

use A2ZWeb\Affiliate\Services\MonthlyCloser;
use Illuminate\Console\Command;

class RecalcPartnerCommand extends Command
{
    protected $signature = 'affiliate:recalc-partner {partner : Partner user id}';

    protected $description = 'Recalculate all closed-month commissions for a partner from program_joined_at onwards. Skips commissions already requested or paid.';

    public function handle(MonthlyCloser $closer): int
    {
        $partnerId = (int) $this->argument('partner');
        $this->info('Recalculating commissions for partner #'.$partnerId);

        $touched = $closer->recalcPartner($partnerId);

        $this->info('Done. Commissions touched: '.$touched);

        return self::SUCCESS;
    }
}
