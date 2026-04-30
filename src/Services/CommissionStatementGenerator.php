<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Models\AffiliateAdjustment;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use A2ZWeb\Affiliate\Models\AffiliateCommissionStatementLine;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommissionStatementGenerator
{
    /**
     * Build a draft statement for the given partner covering the period.
     * Pulls commission rows from `affiliate_commissions` (status closed/requested/paid)
     * and snapshots the active issuing entity from config.
     *
     * Idempotent: if a non-cancelled statement for the same partner+period
     * already exists, it is returned untouched.
     */
    public function generateForPartner(
        AffiliatePartner $partner,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?int $payoutRequestId = null,
    ): AffiliateCommissionStatement {
        if ($periodStart->greaterThan($periodEnd)) {
            throw new RuntimeException('period_start must be before or equal to period_end');
        }

        $existing = AffiliateCommissionStatement::query()
            ->where('partner_user_id', $partner->user_id)
            ->whereIn('payment_status', [
                AffiliateCommissionStatement::STATUS_DRAFT,
                AffiliateCommissionStatement::STATUS_ISSUED,
                AffiliateCommissionStatement::STATUS_PAID,
            ])
            ->get()
            ->first(
                fn ($s) => $s->period_start?->toDateString() === $periodStart->toDateString()
                    && $s->period_end?->toDateString() === $periodEnd->toDateString()
            );

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($partner, $periodStart, $periodEnd, $payoutRequestId): AffiliateCommissionStatement {
            $entity = config('affiliate_statements.issuing_entity');

            $commissions = AffiliateCommission::query()
                ->where('partner_user_id', $partner->user_id)
                ->whereIn('status', [
                    AffiliateCommission::STATUS_CLOSED,
                    AffiliateCommission::STATUS_REQUESTED,
                    AffiliateCommission::STATUS_PAID,
                ])
                ->where(function ($q) use ($periodStart, $periodEnd): void {
                    // commissions whose (year, month) falls in the period
                    $q->whereRaw('(period_year * 100 + period_month) BETWEEN ? AND ?', [
                        $periodStart->year * 100 + $periodStart->month,
                        $periodEnd->year * 100 + $periodEnd->month,
                    ]);
                })
                ->orderBy('period_year')
                ->orderBy('period_month')
                ->orderBy('referred_user_id')
                ->get();

            $rate = $partner->effectiveRevenueShareBp() / 10000;
            $grossTotal = 0;
            $commissionTotal = 0;

            $statement = AffiliateCommissionStatement::create([
                'statement_number' => 'DRAFT-'.uniqid('', true),
                'issuing_entity' => $entity['code'] ?? 'unknown',
                'issuing_entity_legal_name' => (string) ($entity['legal_name'] ?? ''),
                'issuing_entity_company_number' => $entity['company_number'] ?? null,
                'issuing_entity_company_number_label' => $entity['company_number_label'] ?? null,
                'issuing_entity_address' => $entity['address'] ?? null,
                'issuing_entity_country' => $entity['country'] ?? null,
                'issuing_entity_tax_status_note' => $entity['tax_status_note'] ?? null,
                'issuing_entity_statement_prefix' => (string) ($entity['statement_prefix'] ?? 'ACS'),
                'affiliate_snapshot' => $partner->fullSnapshot(),
                'partner_user_id' => $partner->user_id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'currency' => config('affiliate_statements.default_currency', 'usd'),
                'commission_rate' => $rate,
                'gross_revenue_total' => 0,
                'commission_amount' => 0,
                'payment_status' => AffiliateCommissionStatement::STATUS_DRAFT,
                'payout_request_id' => $payoutRequestId,
            ]);

            foreach ($commissions as $c) {
                $grossDecimal = $c->source_amount_cents / 100;
                $lineCommission = $c->commission_amount_cents / 100;

                AffiliateCommissionStatementLine::create([
                    'statement_id' => $statement->id,
                    'transaction_date' => Carbon::create($c->period_year, $c->period_month, 1)->endOfMonth()->toDateString(),
                    'customer_reference' => 'cust-#'.$c->referred_user_id,
                    'subscription_or_invoice_reference' => $c->period_year.'-'.str_pad((string) $c->period_month, 2, '0', STR_PAD_LEFT),
                    'gross_amount' => $grossDecimal,
                    'commission_rate' => $c->commission_rate_bp / 10000,
                    'line_commission' => $lineCommission,
                ]);

                $grossTotal += $grossDecimal;
                $commissionTotal += $lineCommission;
            }

            // Include adjustments. When the statement is being generated for a payout
            // request, pull every adjustment attached to that request. Otherwise fall
            // back to adjustments whose period falls within the statement window.
            $adjustmentsQuery = AffiliateAdjustment::query()
                ->where('partner_user_id', $partner->user_id);

            if ($payoutRequestId) {
                $adjustmentsQuery->where('payout_request_id', $payoutRequestId);
            } else {
                $adjustmentsQuery->whereRaw('(period_year * 100 + period_month) BETWEEN ? AND ?', [
                    $periodStart->year * 100 + $periodStart->month,
                    $periodEnd->year * 100 + $periodEnd->month,
                ]);
            }

            $adjustments = $adjustmentsQuery
                ->orderBy('period_year')
                ->orderBy('period_month')
                ->orderBy('id')
                ->get();

            foreach ($adjustments as $a) {
                $baseDecimal = $a->signedAmountCents() / 100;
                $commissionDecimal = $a->commissionAmountCents() / 100;
                $rate = ((int) $a->commission_rate_bp ?: $partner->effectiveRevenueShareBp()) / 10000;

                AffiliateCommissionStatementLine::create([
                    'statement_id' => $statement->id,
                    'transaction_date' => Carbon::create($a->period_year, $a->period_month, 1)->endOfMonth()->toDateString(),
                    'customer_reference' => 'Manual adjustment',
                    'subscription_or_invoice_reference' => 'adj-'.$a->period_year.'-'.str_pad((string) $a->period_month, 2, '0', STR_PAD_LEFT),
                    'gross_amount' => $baseDecimal,
                    'commission_rate' => $rate,
                    'line_commission' => $commissionDecimal,
                ]);

                $grossTotal += $baseDecimal;
                $commissionTotal += $commissionDecimal;
            }

            $statement->update([
                'gross_revenue_total' => $grossTotal,
                'commission_amount' => $commissionTotal,
            ]);

            return $statement->fresh('lines');
        });
    }
}
