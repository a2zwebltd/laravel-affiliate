<?php

namespace A2ZWeb\Affiliate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateReferral extends Model
{
    protected $table = 'affiliate_referrals';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'attributed_at' => 'datetime',
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
}
