<?php

namespace A2ZWeb\Affiliate\Console\Commands;

use A2ZWeb\Affiliate\Services\MonthlyCloser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CloseMonthCommand extends Command
{
    protected $signature = 'affiliate:close-month
        {--month= : Month to close in YYYY-MM format. Defaults to the previous month.}
        {--partner= : Limit closure to a single partner user id.}';

    protected $description = 'Close affiliate commissions for a fully ended past month.';

    public function handle(MonthlyCloser $closer): int
    {
        $monthOption = $this->option('month');
        if ($monthOption) {
            $target = Carbon::createFromFormat('!Y-m', $monthOption);
        } else {
            $target = Carbon::now()->subMonthNoOverflow();
        }

        $partnerId = $this->option('partner') ? (int) $this->option('partner') : null;

        $this->info('Closing affiliate month '.$target->format('Y-m'));

        $touched = $closer->closeMonth($target->year, $target->month, $partnerId);

        $this->info('Done. Commissions touched: '.$touched);

        return self::SUCCESS;
    }
}
