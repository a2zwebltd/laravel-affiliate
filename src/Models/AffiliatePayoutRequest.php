<?php

namespace A2ZWeb\Affiliate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePayoutRequest extends Model
{
    protected $table = 'affiliate_payout_requests';

    protected $guarded = ['id'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'payout_method_snapshot' => 'array',
            'revenue_share_bp_snapshot' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(config('affiliate.user_model'), 'partner_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(config('affiliate.user_model'), 'decided_by_user_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'payout_request_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AffiliateAdjustment::class, 'payout_request_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(AffiliateCommissionStatement::class, 'payout_request_id');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_REJECTED, self::STATUS_CANCELLED], true);
    }
}
