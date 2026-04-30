<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Marks a payout request paid and produces the matching commission statement
 * (generate → issue → record payment) so the partner receives a formal
 * accounting document with PDF without admins running three actions in sequence.
 */
class PayoutCompletionWorkflow
{
    public function __construct(
        private readonly PayoutRequestService $payoutRequestService,
        private readonly CommissionStatementGenerator $generator,
        private readonly CommissionStatementIssuer $issuer,
        private readonly CommissionStatementPaymentRecorder $recorder,
    ) {}

    public function complete(
        AffiliatePayoutRequest $payoutRequest,
        Model $admin,
        string $paymentReference,
        Carbon $paymentDate,
        string $paymentMethod = 'bank_transfer',
    ): AffiliateCommissionStatement {
        $this->payoutRequestService->markPaid($payoutRequest, $admin, $paymentReference);

        $partner = AffiliatePartner::query()
            ->where('user_id', $payoutRequest->partner_user_id)
            ->first();

        if (! $partner) {
            throw new RuntimeException("No affiliate partner record found for user {$payoutRequest->partner_user_id}.");
        }

        $statement = $this->generator->generateForPartner(
            $partner,
            $payoutRequest->period_start,
            $payoutRequest->period_end,
            (int) $payoutRequest->id,
        );

        if ($statement->payment_status === AffiliateCommissionStatement::STATUS_DRAFT) {
            $this->issuer->issue($statement, allowZero: true);
        }

        $statement = $statement->fresh();

        if ($statement->payment_status === AffiliateCommissionStatement::STATUS_ISSUED) {
            $this->recorder->markPaid(
                $statement,
                $paymentReference,
                $paymentDate,
                $paymentMethod,
            );
        }

        return $statement->fresh();
    }
}
