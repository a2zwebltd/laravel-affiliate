<?php

namespace A2ZWeb\Affiliate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateCommissionStatement extends Model
{
    protected $table = 'affiliate_commission_statements';

    protected $guarded = ['id'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_WISE = 'wise';

    public const METHOD_PAYPAL = 'paypal';

    public const METHOD_OTHER = 'other';

    protected function casts(): array
    {
        return [
            'affiliate_snapshot' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_revenue_total' => 'decimal:4',
            'commission_rate' => 'decimal:4',
            'commission_amount' => 'decimal:4',
            'payment_date' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'sent_to_affiliate_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(config('affiliate.user_model'), 'partner_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AffiliateCommissionStatementLine::class, 'statement_id');
    }

    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayoutRequest::class, 'payout_request_id');
    }

    public function isVisibleToAffiliate(): bool
    {
        return in_array($this->payment_status, [self::STATUS_ISSUED, self::STATUS_PAID], true);
    }

    public function isFinal(): bool
    {
        return in_array($this->payment_status, [self::STATUS_PAID, self::STATUS_CANCELLED], true);
    }
}
