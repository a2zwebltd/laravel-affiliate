<?php

namespace A2ZWeb\Affiliate\Support;

class AdminUrl
{
    public static function partner(int $id): ?string
    {
        return self::buildFromTemplate(config('affiliate.admin_partner_url'), $id);
    }

    public static function payoutRequest(int $id): ?string
    {
        return self::buildFromTemplate(config('affiliate.admin_payout_request_url'), $id);
    }

    public static function statement(int $id): ?string
    {
        return self::buildFromTemplate(config('affiliate.admin_statement_url'), $id);
    }

    private static function buildFromTemplate(?string $template, int $id): ?string
    {
        if (! is_string($template) || $template === '') {
            return null;
        }

        $resolved = str_replace('{id}', (string) $id, $template);

        return str_starts_with($resolved, 'http://') || str_starts_with($resolved, 'https://')
            ? $resolved
            : url($resolved);
    }
}
