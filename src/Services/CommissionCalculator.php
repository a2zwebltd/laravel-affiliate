<?php

namespace A2ZWeb\Affiliate\Services;

class CommissionCalculator
{
    public function commissionCents(int $sourceCents, ?int $rateBp = null): int
    {
        $rateBp ??= (int) config('affiliate.revenue_share_bp', 3000);

        if ($sourceCents <= 0 || $rateBp <= 0) {
            return 0;
        }

        return intdiv($sourceCents * $rateBp, 10000);
    }

    public function rateBp(): int
    {
        return (int) config('affiliate.revenue_share_bp', 3000);
    }
}
