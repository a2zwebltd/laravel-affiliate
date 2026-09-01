<?php

use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use A2ZWeb\Affiliate\Nova\AffiliatePartner as AffiliatePartnerResource;
use A2ZWeb\Affiliate\Nova\AffiliateReferral as AffiliateReferralResource;
use A2ZWeb\Affiliate\Tests\User;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class NovaTestUserResource extends Resource
{
    public static string $model = User::class;

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}

beforeEach(function () {
    config()->set('affiliate.nova.user_resource', NovaTestUserResource::class);
});

/**
 * @return array{partner: AffiliatePartner, referred: User, otherReferred: User}
 */
function makePartnerWithReferralsAndCommissions(): array
{
    $partnerUser = User::create(['name' => 'Partner', 'email' => 'partner@example.com']);
    $referred = User::create(['name' => 'Referred', 'email' => 'referred@example.com']);
    $otherReferred = User::create(['name' => 'Other', 'email' => 'other@example.com']);

    $partner = AffiliatePartner::create([
        'user_id' => $partnerUser->id,
        'code' => 'PARTNER1',
        'status' => AffiliatePartner::STATUS_APPROVED,
        'program_joined_at' => now()->subYear(),
    ]);

    foreach ([$referred, $otherReferred] as $user) {
        AffiliateReferral::create([
            'partner_user_id' => $partnerUser->id,
            'referred_user_id' => $user->id,
            'code_used' => 'PARTNER1',
            'attributed_at' => now()->subMonths(6),
        ]);
    }

    $commission = fn (int $userId, int $month, int $sourceCents, string $status) => AffiliateCommission::create([
        'partner_user_id' => $partnerUser->id,
        'referred_user_id' => $userId,
        'period_year' => 2026,
        'period_month' => $month,
        'source_amount_cents' => $sourceCents,
        'commission_amount_cents' => (int) ($sourceCents * 0.3),
        'commission_rate_bp' => 3000,
        'currency' => 'usd',
        'status' => $status,
    ]);

    $commission($referred->id, 1, 10000, AffiliateCommission::STATUS_CLOSED);
    $commission($referred->id, 2, 5000, AffiliateCommission::STATUS_PAID);
    $commission($otherReferred->id, 1, 2500, AffiliateCommission::STATUS_REQUESTED);
    // Reversed commissions must not count towards referred revenue.
    $commission($otherReferred->id, 2, 99900, AffiliateCommission::STATUS_REVERSED);

    return ['partner' => $partner, 'referred' => $referred, 'otherReferred' => $otherReferred];
}

function resolveNovaField(object $resource, mixed $model, string $name): Field
{
    $field = collect($resource->fields(app(NovaRequest::class)))
        ->first(fn ($field) => $field instanceof Field && $field->name === $name);

    expect($field)->not->toBeNull();

    $field->resolve($model);

    return $field;
}

it('shows referral count and referred revenue on the partner resource', function () {
    ['partner' => $partner] = makePartnerWithReferralsAndCommissions();

    $resource = new AffiliatePartnerResource($partner);

    expect(resolveNovaField($resource, $partner, __('Referrals'))->value)->toBe(2);
    expect(resolveNovaField($resource, $partner, __('Referred revenue'))->value)->toBe('$175.00');
    expect(resolveNovaField($resource, $partner, __('Total Earned (cents)'))->value)->toBe(5250);
});

it('shows attributed revenue per referral on the referral resource', function () {
    ['referred' => $referred, 'otherReferred' => $otherReferred] = makePartnerWithReferralsAndCommissions();

    $first = AffiliateReferral::where('referred_user_id', $referred->id)->firstOrFail();
    $second = AffiliateReferral::where('referred_user_id', $otherReferred->id)->firstOrFail();

    expect(resolveNovaField(new AffiliateReferralResource($first), $first, __('Attributed revenue'))->value)->toBe('$150.00');
    expect(resolveNovaField(new AffiliateReferralResource($second), $second, __('Attributed revenue'))->value)->toBe('$25.00');
});
