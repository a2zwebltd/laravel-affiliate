<?php

namespace A2ZWeb\Affiliate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommissionStatementLine extends Model
{
    protected $table = 'affiliate_commission_statement_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'gross_amount' => 'decimal:4',
            'commission_rate' => 'decimal:4',
            'line_commission' => 'decimal:4',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(AffiliateCommissionStatement::class, 'statement_id');
    }
}
