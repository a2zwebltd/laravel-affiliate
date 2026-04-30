<?php

use A2ZWeb\Affiliate\Support\AdminUrl;
use Illuminate\Support\Facades\URL;

it('returns null when no template is configured', function () {
    config()->set('affiliate.admin_partner_url', null);
    expect(AdminUrl::partner(42))->toBeNull();
});

it('builds an absolute URL from a relative template', function () {
    URL::forceRootUrl('https://example.test');
    URL::forceScheme('https');
    config()->set('affiliate.admin_partner_url', '/nova/resources/affiliate-partners/{id}');
    expect(AdminUrl::partner(42))->toBe('https://example.test/nova/resources/affiliate-partners/42');
});

it('keeps fully qualified template URLs intact', function () {
    config()->set('affiliate.admin_payout_request_url', 'https://admin.other.test/payouts/{id}/edit');
    expect(AdminUrl::payoutRequest(7))->toBe('https://admin.other.test/payouts/7/edit');
});
