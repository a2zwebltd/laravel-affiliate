<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Events\StatementIssued;
use A2ZWeb\Affiliate\Jobs\GenerateCommissionStatementPdf;
use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

class CommissionStatementIssuer
{
    public function __construct(private readonly StatementNumberGenerator $numbers) {}

    public function issue(AffiliateCommissionStatement $statement, bool $allowZero = false): AffiliateCommissionStatement
    {
        if ($statement->payment_status !== AffiliateCommissionStatement::STATUS_DRAFT) {
            throw new RuntimeException('Only draft statements can be issued.');
        }

        if (! $allowZero && (float) $statement->commission_amount <= 0) {
            throw new RuntimeException('Statement has no commission lines (zero amount). Pass allowZero to override.');
        }

        DB::transaction(function () use ($statement): void {
            $number = $this->numbers->next($statement->issuing_entity_statement_prefix);
            $statement->forceFill([
                'statement_number' => $number,
                'payment_status' => AffiliateCommissionStatement::STATUS_ISSUED,
                'issued_at' => Carbon::now(),
            ])->save();
        });

        $statement->refresh();

        GenerateCommissionStatementPdf::dispatch($statement->id)
            ->onQueue(config('affiliate_statements.pdf.queue', 'default'));

        Event::dispatch(new StatementIssued($statement));

        return $statement;
    }

    public function cancel(AffiliateCommissionStatement $statement, ?string $reason = null): AffiliateCommissionStatement
    {
        if ($statement->payment_status !== AffiliateCommissionStatement::STATUS_DRAFT) {
            throw new RuntimeException('Only draft statements can be cancelled.');
        }

        $statement->update([
            'payment_status' => AffiliateCommissionStatement::STATUS_CANCELLED,
            'notes' => trim((string) $statement->notes."\nCancelled: ".$reason),
        ]);

        return $statement;
    }
}
