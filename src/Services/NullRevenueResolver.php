<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Contracts\RevenueResolver;

class NullRevenueResolver implements RevenueResolver
{
    public function revenueForUserInMonth(int $userId, int $year, int $month): int
    {
        return 0;
    }
}
