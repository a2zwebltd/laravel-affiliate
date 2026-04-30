<?php

namespace A2ZWeb\Affiliate\Events;

use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;

class PayoutApproved
{
    public function __construct(public AffiliatePayoutRequest $request) {}
}
