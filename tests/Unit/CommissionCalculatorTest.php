<?php

use A2ZWeb\Affiliate\Services\CommissionCalculator;

it('computes commission as basis points of source revenue', function () {
    $calc = new CommissionCalculator;

    expect($calc->commissionCents(10000, 3000))->toBe(3000);
    expect($calc->commissionCents(9999, 3000))->toBe(2999);
    expect($calc->commissionCents(0, 3000))->toBe(0);
    expect($calc->commissionCents(10000, 0))->toBe(0);
});

it('reads default rate from config', function () {
    config()->set('affiliate.revenue_share_bp', 2500);
    $calc = new CommissionCalculator;

    expect($calc->commissionCents(10000))->toBe(2500);
});
