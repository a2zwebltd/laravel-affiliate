<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Contracts\ReferredUserInfoResolver;

class NullReferredUserInfoResolver implements ReferredUserInfoResolver
{
    public function infoFor(int $userId): array
    {
        return [
            'display_name' => 'User #'.$userId,
            'is_paying' => false,
            'plan' => null,
        ];
    }
}
