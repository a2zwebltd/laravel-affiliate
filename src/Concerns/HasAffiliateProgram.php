<?php

namespace A2ZWeb\Affiliate\Concerns;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use A2ZWeb\Affiliate\Services\AffiliateCodeGenerator;
use A2ZWeb\Affiliate\Services\EligibilityChecker;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasAffiliateProgram
{
    public function affiliatePartner(): HasOne
    {
        return $this->hasOne(AffiliatePartner::class, 'user_id');
    }

    public function affiliateReferralsAsPartner(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class, 'partner_user_id');
    }

    public function affiliateReferralAsReferred(): HasOne
    {
        return $this->hasOne(AffiliateReferral::class, 'referred_user_id');
    }

    public function isAffiliateApproved(): bool
    {
        return (bool) $this->affiliatePartner?->isApproved();
    }

    public function isAffiliateEligibleToApply(): bool
    {
        return app(EligibilityChecker::class)->isEligibleToApply($this);
    }

    public function affiliateCode(): ?string
    {
        return $this->affiliatePartner?->code ?? $this->ensureAffiliateCode();
    }

    /**
     * Defaults used to pre-fill the affiliate application form. Override in
     * your User model to map your project's profile fields (e.g. billing
     * address). Keys: email, phone, address, country_of_tax_residence, full_name.
     *
     * @return array<string, ?string>
     */
    public function affiliateApplyDefaults(): array
    {
        return [
            'email' => $this->email ?? null,
            'phone' => $this->phone ?? null,
            'address' => $this->address ?? null,
            'country_of_tax_residence' => $this->country ?? null,
            'full_name' => $this->name ?? null,
        ];
    }

    public function affiliateLink(?string $base = null): ?string
    {
        $code = $this->affiliateCode();
        if (! $code) {
            return null;
        }

        $base = rtrim($base ?? config('app.url'), '/');
        $param = config('affiliate.query_param', 'aff');

        return $base.'/?'.$param.'='.urlencode($code);
    }

    /**
     * Lazily create a "share-only" affiliate_partners row with status=pending and applied_at=null.
     * The user can already share their link to gather the required referrals; only when they
     * submit the apply form do we set applied_at, payout details and T&C acceptance.
     */
    private function ensureAffiliateCode(): ?string
    {
        $existing = $this->affiliatePartner()->first();
        if ($existing) {
            $this->setRelation('affiliatePartner', $existing);

            return $existing->code;
        }

        $code = app(AffiliateCodeGenerator::class)
            ->generate((int) $this->getKey());

        $partner = AffiliatePartner::create([
            'user_id' => $this->getKey(),
            'code' => $code,
            'status' => AffiliatePartner::STATUS_PENDING,
        ]);

        $this->setRelation('affiliatePartner', $partner);

        return $code;
    }
}
