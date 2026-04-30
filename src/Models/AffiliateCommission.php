<?php

namespace A2ZWeb\Affiliate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    protected $table = 'affiliate_commissions';

    protected $guarded = ['id'];

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_PAID = 'paid';

    public const STATUS_REVERSED = 'reversed';

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(config('affiliate.user_model'), 'partner_user_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(config('affiliate.user_model'), 'referred_user_id');
    }

    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayoutRequest::class, 'payout_request_id');
    }
}
