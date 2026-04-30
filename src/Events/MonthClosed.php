<?php

namespace A2ZWeb\Affiliate\Events;

class MonthClosed
{
    public function __construct(
        public int $year,
        public int $month,
        public int $commissionsTouched,
    ) {}
}
