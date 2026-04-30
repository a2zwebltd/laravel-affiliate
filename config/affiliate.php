<?php

use App\Models\User;

return [
    'user_model' => env('AFFILIATE_USER_MODEL', User::class),

    'currency' => env('AFFILIATE_CURRENCY', 'usd'),

    /*
     * ISO-4217 currency codes (lowercase) the platform supports for commissions,
     * statements, payout requests and admin adjustments. Override via
     * AFFILIATE_CURRENCIES env (comma-separated) without editing this file.
     */
    'currencies' => array_values(array_filter(array_map(
        fn (string $code): string => strtolower(trim($code)),
        explode(',', (string) env('AFFILIATE_CURRENCIES', 'usd,eur,gbp,pln,aud,cad'))
    ))),

    'revenue_share_bp' => (int) env('AFFILIATE_REVENUE_SHARE_BP', 3000),

    'min_referred_users' => (int) env('AFFILIATE_MIN_REFERRED_USERS', 2),

    'min_payout_cents' => (int) env('AFFILIATE_MIN_PAYOUT_CENTS', 5000),

    'cookie_name' => env('AFFILIATE_COOKIE_NAME', 'sa_aff'),

    'cookie_ttl_days' => (int) env('AFFILIATE_COOKIE_TTL_DAYS', 60),

    'query_param' => env('AFFILIATE_QUERY_PARAM', 'aff'),

    'attribution' => env('AFFILIATE_ATTRIBUTION', 'first_touch'),

    'allow_self_referral' => (bool) env('AFFILIATE_ALLOW_SELF_REFERRAL', false),

    'cache_ttl_seconds' => (int) env('AFFILIATE_CACHE_TTL_SECONDS', 900),

    'admin_notification_email' => env('AFFILIATE_ADMIN_EMAIL'),

    'invoice_disk' => env('AFFILIATE_INVOICE_DISK', 'local'),

    /*
     * Optional URL templates for "Review in admin panel" CTAs in notification emails.
     * Use the literal placeholder `{id}` — it is replaced with the resource id and
     * passed to Laravel's `url()` helper so relative paths get the absolute base.
     * Leave null to omit the CTA button (e.g. apps without an admin panel).
     */
    'admin_partner_url' => env('AFFILIATE_ADMIN_PARTNER_URL'),
    'admin_payout_request_url' => env('AFFILIATE_ADMIN_PAYOUT_REQUEST_URL'),
    'admin_statement_url' => env('AFFILIATE_ADMIN_STATEMENT_URL'),

    'layout' => env('AFFILIATE_LAYOUT', 'layouts.app'),

    /*
     * View used by StatementController::show() for the partner-facing statement
     * detail page. Override to a host-app view (e.g. 'affiliate.partner-statement')
     * if your app ships a custom blade with its own layout component.
     */
    'partner_statement_view' => env('AFFILIATE_PARTNER_STATEMENT_VIEW', 'affiliate::partner-statement'),

    'terms' => [
        'general_url' => env('AFFILIATE_GENERAL_TERMS_URL'),
        'general_version' => env('AFFILIATE_GENERAL_TERMS_VERSION', '1'),
        'affiliate_url' => env('AFFILIATE_AFFILIATE_TERMS_URL'),
        'affiliate_version' => env('AFFILIATE_AFFILIATE_TERMS_VERSION', '1'),
    ],

    'commission_window' => [
        'mode' => env('AFFILIATE_WINDOW_MODE', 'lifetime'),
        'months' => (int) env('AFFILIATE_WINDOW_MONTHS', 12),
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => env('AFFILIATE_ROUTES_PREFIX', 'dashboard/affiliate'),
        'name_prefix' => env('AFFILIATE_ROUTES_NAME_PREFIX', 'affiliate.'),
        'middleware' => array_values(array_filter(array_map('trim', explode(
            ',',
            (string) env('AFFILIATE_ROUTES_MIDDLEWARE', 'web,auth')
        )))),
    ],

    'capture_middleware_enabled' => true,

    'resolvers' => [
        'revenue' => null,
        'referred_user_info' => null,
    ],

    /*
     * Nova-specific configuration. Only consulted when laravel/nova is installed
     * and the host registers package resources via NovaIntegration::resources().
     */
    'nova' => [
        'user_resource' => env('AFFILIATE_NOVA_USER_RESOURCE', 'App\\Nova\\User'),
        'menu_label' => env('AFFILIATE_NOVA_MENU_LABEL', 'Affiliate Program'),
        'menu_icon' => env('AFFILIATE_NOVA_MENU_ICON', 'currency-dollar'),
    ],
];
