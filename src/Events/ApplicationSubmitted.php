<?php

namespace A2ZWeb\Affiliate\Events;

use A2ZWeb\Affiliate\Models\AffiliatePartner;

class ApplicationSubmitted
{
    public function __construct(public AffiliatePartner $partner) {}
}
