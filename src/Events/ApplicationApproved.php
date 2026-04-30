<?php

namespace A2ZWeb\Affiliate\Events;

use A2ZWeb\Affiliate\Models\AffiliatePartner;

class ApplicationApproved
{
    public function __construct(public AffiliatePartner $partner) {}
}
