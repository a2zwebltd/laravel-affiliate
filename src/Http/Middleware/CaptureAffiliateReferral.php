<?php

namespace A2ZWeb\Affiliate\Http\Middleware;

use A2ZWeb\Affiliate\Services\ReferralAttributor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAffiliateReferral
{
    public function __construct(private readonly ReferralAttributor $attributor) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->query(config('affiliate.query_param', 'aff'))) {
            $this->attributor->captureFromRequest($request);
        }

        return $next($request);
    }
}
