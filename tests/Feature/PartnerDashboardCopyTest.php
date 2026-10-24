<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;

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

it('names the host product in the share texts and keeps a translated apostrophe inside the JS string', function () {
    config()->set('app.name', "Avocat d'Or");

    $html = view('affiliate::partials.share-buttons', ['link' => 'https://example.test/?aff=X'])->render();

    expect($html)
        ->not->toContain('SharpAPI')
        ->toContain('Try Avocat')
        ->not->toContain("Try Avocat d'Or");
});

it('names months in the reader\'s language on the partner screens', function () {
    Carbon::setLocale('pl');

    $html = Blade::render("{{ now()->setDate(2026, 9, 1)->translatedFormat('F Y') }}");

    expect($html)->toBe('wrzesień 2026')
        ->and(file_get_contents(__DIR__.'/../../resources/views/blade/partials/state-approved.blade.php'))->not->toMatch("/->format\\('[MF]/");

    Carbon::setLocale('en');
});
