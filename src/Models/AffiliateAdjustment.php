<?php

namespace A2ZWeb\Affiliate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateAdjustment extends Model
{
    protected $table = 'affiliate_adjustments';

    protected $guarded = ['id'];

    public const TYPE_ADDITION = 'addition';

    public const TYPE_SUBTRACTION = 'subtraction';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_PAID = 'paid';

    protected static function booted(): void
    {
        static::creating(function (self $adjustment): void {
            if ((int) ($adjustment->commission_rate_bp ?? 0) <= 0) {
                $partner = AffiliatePartner::query()
                    ->where('user_id', $adjustment->partner_user_id)
                    ->first();

                $adjustment->commission_rate_bp = $partner
                    ? $partner->effectiveRevenueShareBp()
                    : (int) config('affiliate.revenue_share_bp', 3000);
            }
        });
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(config('affiliate.user_model'), 'partner_user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(config('affiliate.user_model'), 'admin_user_id');
    }

    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayoutRequest::class, 'payout_request_id');
    }

    /**
     * Signed adjustment **base** amount (the change to source revenue).
     * Additions positive, subtractions negative.
     */
    public function signedAmountCents(): int
    {
        return $this->type === self::TYPE_ADDITION
            ? (int) $this->amount_cents
            : -(int) $this->amount_cents;
    }

    /**
     * Signed **commission impact** of this adjustment (the change to the partner's
     * commission cents). Equals `signed_base × commission_rate_bp / 10000` using
     * the rate snapshotted on the adjustment row.
     */
    public function commissionAmountCents(): int
    {
        $bp = (int) ($this->commission_rate_bp ?: 0);
        if ($bp <= 0) {
            $bp = (int) config('affiliate.revenue_share_bp', 3000);
        }

        return (int) round($this->signedAmountCents() * $bp / 10000);
    }
}
