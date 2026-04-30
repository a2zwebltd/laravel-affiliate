<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use Illuminate\Support\Str;

class AffiliateCodeGenerator
{
    public function generate(int $userId): string
    {
        $base = strtoupper(Str::random(8));

        $candidate = $base;
        $suffix = 0;
        while (AffiliatePartner::query()->where('code', $candidate)->exists()) {
            $suffix++;
            $candidate = $base.$suffix;
        }

        return $candidate;
    }
}
