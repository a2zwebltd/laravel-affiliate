<?php

namespace A2ZWeb\Affiliate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePartner extends Model
{
    protected $table = 'affiliate_partners';

    protected $guarded = ['id'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    public const PAYOUT_PAYPAL = 'paypal';

    public const PAYOUT_BANK = 'bank_transfer';

    protected function casts(): array
    {
        return [
            'is_company' => 'boolean',
            'applied_at' => 'datetime',
            'decided_at' => 'datetime',
            'program_joined_at' => 'date',
            'accepted_terms_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('affiliate.user_model'), 'user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(config('affiliate.user_model'), 'decided_by_user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class, 'partner_user_id', 'user_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'partner_user_id', 'user_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AffiliateAdjustment::class, 'partner_user_id', 'user_id');
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(AffiliatePayoutRequest::class, 'partner_user_id', 'user_id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function payoutDetailsComplete(): bool
    {
        return match ($this->payout_method) {
            self::PAYOUT_PAYPAL => filled($this->paypal_email),
            self::PAYOUT_BANK => filled($this->bank_account_holder) && filled($this->bank_iban),
            default => false,
        };
    }

    public function effectiveRevenueShareBp(): int
    {
        return (int) ($this->revenue_share_bp ?? config('affiliate.revenue_share_bp', 3000));
    }

    public function payoutSnapshot(): array
    {
        return [
            'method' => $this->payout_method,
            'paypal_email' => $this->paypal_email,
            'bank_account_holder' => $this->bank_account_holder,
            'bank_iban' => $this->bank_iban,
            'bank_swift' => $this->bank_swift,
            'bank_address' => $this->bank_address,
            'is_company' => (bool) $this->is_company,
            'company_name' => $this->company_name,
            'tax_id' => $this->tax_id,
        ];
    }

    /**
     * Full audit snapshot — billing details + payout details.
     * Used by AffiliateCommissionStatement so future profile edits don't
     * mutate historical statements.
     */
    public function fullSnapshot(): array
    {
        return [
            'billing_full_name' => $this->billing_full_name,
            'billing_address' => $this->billing_address,
            'country_of_tax_residence' => $this->country_of_tax_residence,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'is_company' => (bool) $this->is_company,
            'company_name' => $this->company_name,
            'tax_id' => $this->tax_id,
            'partner_code' => $this->code,
        ] + $this->payoutSnapshot();
    }
}
