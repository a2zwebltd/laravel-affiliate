<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Events\StatementPaid;
use A2ZWeb\Affiliate\Jobs\GenerateCommissionStatementPdf;
use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RuntimeException;

class CommissionStatementPaymentRecorder
{
    public function markPaid(
        AffiliateCommissionStatement $statement,
        string $paymentReference,
        Carbon $paymentDate,
        string $paymentMethod,
    ): AffiliateCommissionStatement {
        if ($statement->payment_status !== AffiliateCommissionStatement::STATUS_ISSUED) {
            throw new RuntimeException('Only issued statements can be marked as paid.');
        }

        $statement->update([
            'payment_status' => AffiliateCommissionStatement::STATUS_PAID,
            'payment_reference' => $paymentReference,
            'payment_date' => $paymentDate->toDateString(),
            'payment_method' => $paymentMethod,
            'paid_at' => Carbon::now(),
        ]);

        // Regenerate PDF (now includes payment reference + date) — overwrite same path on disk.
        GenerateCommissionStatementPdf::dispatch($statement->id)
            ->onQueue(config('affiliate_statements.pdf.queue', 'default'));

        Event::dispatch(new StatementPaid($statement->fresh()));

        return $statement->fresh();
    }
}
