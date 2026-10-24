<?php

/*
 * The "become a partner" card states the program's own terms: a host with no
 * referral threshold must not show "Invite at least 0 signups" and a 0/0
 * progress bar, and a host paying for a fixed number of months must not
 * promise a lifetime commission.
 */
function renderNoPartnerCard(array $overrides = []): string
{
    return view('affiliate::partials.state-no-partner', array_merge([
        'eligible' => true,
        'referrals_needed' => 0,
        'referred_count' => 0,
        'min_referred_users' => 0,
    ], $overrides))->render();
}

it('offers the application at once when no referrals are required', function () {
    $html = renderNoPartnerCard();

    expect($html)
        ->toContain('You can apply right away')
        ->not->toContain('Invite at least')
        ->not->toContain('Progress');
});

it('keeps the referral threshold and its progress bar when one is set', function () {
    $html = renderNoPartnerCard(['eligible' => false, 'referrals_needed' => 1, 'referred_count' => 1, 'min_referred_users' => 2]);

    expect($html)->toContain('Invite at least 2 signups')->toContain('1 / 2');
});

it('describes a commission window in months instead of a lifetime one', function () {
    config()->set('affiliate.commission_window', ['mode' => 'months', 'months' => 12]);

    expect(renderNoPartnerCard())->toContain('Commission for 12 months')->not->toContain('Lifetime commission');

    config()->set('affiliate.commission_window', ['mode' => 'lifetime', 'months' => 12]);

    expect(renderNoPartnerCard())->toContain('Lifetime commission');
});
