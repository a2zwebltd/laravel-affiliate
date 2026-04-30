<?php

namespace A2ZWeb\Affiliate\Contracts;

interface ReferredUserInfoResolver
{
    /**
     * Return display info about a referred user that the package can show
     * in the partner dashboard. Implementations should NEVER expose
     * sensitive data (e.g. raw revenue, job counts).
     *
     * @return array{display_name:string,is_paying:bool,plan:?string}
     */
    public function infoFor(int $userId): array;
}
