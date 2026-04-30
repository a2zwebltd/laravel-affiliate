<?php

return [
    /*
     * Active issuing entity. Only ONE entity is active at any given time.
     * Historical statements snapshot these values, so when the operator
     * later replaces these env vars (e.g. ownership transfer), past
     * statements still show their original origin.
     *
     * Defaults are generic Acme Ltd placeholders — host application
     * must override via env to use its real legal info.
     */
    'issuing_entity' => [
        'code' => env('AFFILIATE_STATEMENTS_ENTITY_CODE', 'acme_ltd'),
        'legal_name' => env('AFFILIATE_STATEMENTS_LEGAL_NAME', 'Acme Ltd.'),
        'company_number' => env('AFFILIATE_STATEMENTS_COMPANY_NUMBER', '00000000'),
        'company_number_label' => env('AFFILIATE_STATEMENTS_COMPANY_NUMBER_LABEL', 'Company Number'),
        'address' => env('AFFILIATE_STATEMENTS_ADDRESS', '123 Example Street, Example City, EX1 2AB'),
        'country' => env('AFFILIATE_STATEMENTS_COUNTRY', 'United Kingdom'),
        'tax_status_note' => env(
            'AFFILIATE_STATEMENTS_TAX_STATUS_NOTE',
            'Acme Ltd. is not VAT/GST registered. No VAT or GST applies to this statement.'
        ),
        'statement_prefix' => env('AFFILIATE_STATEMENTS_PREFIX', 'ACS'),
        'support_email' => env('AFFILIATE_STATEMENTS_SUPPORT_EMAIL'),
    ],

    'default_currency' => env('AFFILIATE_STATEMENTS_CURRENCY', 'usd'),

    'admin_notification_email' => env('AFFILIATE_STATEMENTS_ADMIN_EMAIL'),

    'pdf' => [
        'disk' => env('AFFILIATE_STATEMENTS_DISK', 'local'),
        'queue' => env('AFFILIATE_STATEMENTS_QUEUE', 'default'),
    ],

    'agreement' => [
        'version' => env('AFFILIATE_STATEMENTS_AGREEMENT_VERSION', '1.0'),
        'date' => env('AFFILIATE_STATEMENTS_AGREEMENT_DATE'),
        'product_name' => env('AFFILIATE_STATEMENTS_PRODUCT_NAME', 'subscriptions'),
    ],
];
