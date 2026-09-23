<?php

return [
    /*
     * Active issuing entity. Only ONE entity is active at any given time.
     * Historical statements snapshot these values, so when the operator
     * later replaces these env vars (e.g. ownership transfer), past
     * statements still show their original origin.
     *
     * `code` and `legal_name` are required: generating or issuing a statement
     * throws IssuingEntityNotConfigured (and logs an error) while either is
     * empty, or while any field still holds the "Acme Ltd." placeholder that
     * versions before 1.3.0 shipped as defaults.
     */
    'issuing_entity' => [
        'code' => env('AFFILIATE_STATEMENTS_ENTITY_CODE'),
        'legal_name' => env('AFFILIATE_STATEMENTS_LEGAL_NAME'),
        'company_number' => env('AFFILIATE_STATEMENTS_COMPANY_NUMBER'),
        'company_number_label' => env('AFFILIATE_STATEMENTS_COMPANY_NUMBER_LABEL', 'Company Number'),
        'address' => env('AFFILIATE_STATEMENTS_ADDRESS'),
        'country' => env('AFFILIATE_STATEMENTS_COUNTRY'),
        'tax_status_note' => env('AFFILIATE_STATEMENTS_TAX_STATUS_NOTE'),
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
