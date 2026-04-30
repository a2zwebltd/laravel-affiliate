<?php

namespace A2ZWeb\Affiliate\Events;

use A2ZWeb\Affiliate\Models\AffiliatePartner;

class ApplicationRejected
{
    public function __construct(public AffiliatePartner $partner, public ?string $reason = null) {}
}
