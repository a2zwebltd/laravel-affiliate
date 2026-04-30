<?php

namespace A2ZWeb\Affiliate\Events;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;

class StatementIssued
{
    public function __construct(public AffiliateCommissionStatement $statement) {}
}
