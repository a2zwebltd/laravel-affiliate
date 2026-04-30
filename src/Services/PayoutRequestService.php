<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Events\PayoutApproved;
use A2ZWeb\Affiliate\Events\PayoutPaid;
use A2ZWeb\Affiliate\Events\PayoutRequested;
use A2ZWeb\Affiliate\Models\AffiliateAdjustment;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;

class PayoutRequestService
{
    public function __construct(
        private readonly PartnerStatistics $statistics,
    ) {}

    public function create(
        AffiliatePartner $partner,
        ?UploadedFile $invoice = null,
        ?string $purchaseOrderId = null,
    ): AffiliatePayoutRequest {
        if (! $partner->isApproved()) {
            throw new RuntimeException('Partner is not approved.');
        }

        if (! $partner->payoutDetailsComplete()) {
            throw new RuntimeException('Payout details are not complete.');
        }

        $hasOpen = AffiliatePayoutRequest::query()
            ->where('partner_user_id', $partner->user_id)
            ->whereIn('status', [
                AffiliatePayoutRequest::STATUS_PENDING,
                AffiliatePayoutRequest::STATUS_APPROVED,
            ])
            ->exists();
        if ($hasOpen) {
            throw new RuntimeException('You already have an open payout request.');
        }

        return DB::transaction(function () use ($partner, $invoice, $purchaseOrderId): AffiliatePayoutRequest {
            $commissions = AffiliateCommission::query()
                ->where('partner_user_id', $partner->user_id)
                ->where('status', AffiliateCommission::STATUS_CLOSED)
                ->orderBy('period_year')
                ->orderBy('period_month')
                ->lockForUpdate()
                ->get();

            if ($commissions->isEmpty()) {
                throw new RuntimeException('No closed commissions available to request.');
            }

            $gross = (int) $commissions->sum('commission_amount_cents');

            $minMonth = Carbon::create((int) $commissions->min('period_year'), (int) $commissions->min('period_month'), 1);
            $maxMonth = Carbon::create((int) $commissions->max('period_year'), (int) $commissions->max('period_month'), 1)->endOfMonth();

            $adjustments = AffiliateAdjustment::query()
                ->where('partner_user_id', $partner->user_id)
                ->where('status', AffiliateAdjustment::STATUS_CLOSED)
                ->whereRaw('(period_year * 100 + period_month) BETWEEN ? AND ?', [
                    $minMonth->year * 100 + $minMonth->month,
                    $maxMonth->year * 100 + $maxMonth->month,
                ])
                ->lockForUpdate()
                ->get();

            $adjustmentsTotal = $adjustments->reduce(
                fn (int $sum, AffiliateAdjustment $a): int => $sum + $a->commissionAmountCents(),
                0,
            );

            $net = $gross + $adjustmentsTotal;

            if ($net < (int) config('affiliate.min_payout_cents', 5000)) {
                throw new RuntimeException('Available amount is below the minimum payout threshold.');
            }

            $request = AffiliatePayoutRequest::create([
                'partner_user_id' => $partner->user_id,
                'status' => AffiliatePayoutRequest::STATUS_PENDING,
                'period_start' => $minMonth->toDateString(),
                'period_end' => $maxMonth->toDateString(),
                'gross_amount_cents' => $gross,
                'adjustments_amount_cents' => $adjustmentsTotal,
                'net_amount_cents' => $net,
                'currency' => config('affiliate.currency', 'usd'),
                'payout_method_snapshot' => $partner->payoutSnapshot(),
                'revenue_share_bp_snapshot' => $partner->effectiveRevenueShareBp(),
                'requested_at' => Carbon::now(),
                'purchase_order_id' => $purchaseOrderId ?: null,
            ]);

            if ($invoice instanceof UploadedFile) {
                $disk = config('affiliate.invoice_disk', 'local');
                $original = $invoice->getClientOriginalName();
                $stored = $invoice->storeAs(
                    'affiliate-payout-invoices/'.$partner->user_id,
                    $request->id.'-'.Str::random(6).'.pdf',
                    ['disk' => $disk],
                );
                $request->update([
                    'invoice_disk' => $disk,
                    'invoice_file_path' => $stored,
                    'invoice_original_filename' => substr((string) $original, 0, 191),
                ]);
            }

            AffiliateCommission::query()
                ->whereIn('id', $commissions->pluck('id'))
                ->update([
                    'status' => AffiliateCommission::STATUS_REQUESTED,
                    'payout_request_id' => $request->id,
                ]);

            if ($adjustments->isNotEmpty()) {
                AffiliateAdjustment::query()
                    ->whereIn('id', $adjustments->pluck('id'))
                    ->update([
                        'status' => AffiliateAdjustment::STATUS_REQUESTED,
                        'payout_request_id' => $request->id,
                    ]);
            }

            $this->statistics->forget((int) $partner->user_id);
            Event::dispatch(new PayoutRequested($request));

            return $request;
        });
    }

    public function cancel(AffiliatePayoutRequest $request): void
    {
        if ($request->status !== AffiliatePayoutRequest::STATUS_PENDING) {
            throw new RuntimeException('Only pending requests can be cancelled.');
        }

        $this->revertCommissionsAndAdjustments($request);
        $request->update(['status' => AffiliatePayoutRequest::STATUS_CANCELLED]);
        $this->statistics->forget((int) $request->partner_user_id);
    }

    public function approve(AffiliatePayoutRequest $request, Model $admin): void
    {
        if ($request->status !== AffiliatePayoutRequest::STATUS_PENDING) {
            throw new RuntimeException('Only pending requests can be approved.');
        }

        $this->recalculateTotals($request);

        $request->update([
            'status' => AffiliatePayoutRequest::STATUS_APPROVED,
            'decided_by_user_id' => $admin->getKey(),
            'decided_at' => Carbon::now(),
        ]);

        Event::dispatch(new PayoutApproved($request));
        $this->statistics->forget((int) $request->partner_user_id);
    }

    /**
     * Re-sum gross + adjustments from the linked rows. Folds in adjustments
     * the admin attached *after* the request was created.
     */
    public function recalculateTotals(AffiliatePayoutRequest $request): void
    {
        $gross = (int) AffiliateCommission::query()
            ->where('payout_request_id', $request->id)
            ->sum('commission_amount_cents');

        $adjustmentsTotal = AffiliateAdjustment::query()
            ->where('payout_request_id', $request->id)
            ->get()
            ->reduce(
                fn (int $sum, AffiliateAdjustment $a): int => $sum + $a->commissionAmountCents(),
                0,
            );

        $request->update([
            'gross_amount_cents' => $gross,
            'adjustments_amount_cents' => $adjustmentsTotal,
            'net_amount_cents' => $gross + $adjustmentsTotal,
        ]);
    }

    public function reject(AffiliatePayoutRequest $request, Model $admin, string $reason): void
    {
        if (! in_array($request->status, [AffiliatePayoutRequest::STATUS_PENDING, AffiliatePayoutRequest::STATUS_APPROVED], true)) {
            throw new RuntimeException('This request cannot be rejected.');
        }

        $this->revertCommissionsAndAdjustments($request);

        $request->update([
            'status' => AffiliatePayoutRequest::STATUS_REJECTED,
            'decided_by_user_id' => $admin->getKey(),
            'decided_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ]);

        $this->statistics->forget((int) $request->partner_user_id);
    }

    public function markPaid(AffiliatePayoutRequest $request, Model $admin, string $paymentReference): void
    {
        if ($request->status !== AffiliatePayoutRequest::STATUS_APPROVED) {
            throw new RuntimeException('Only approved requests can be marked as paid.');
        }

        DB::transaction(function () use ($request, $admin, $paymentReference): void {
            $this->recalculateTotals($request);

            $request->update([
                'status' => AffiliatePayoutRequest::STATUS_PAID,
                'payment_reference' => $paymentReference,
                'paid_at' => Carbon::now(),
                'decided_by_user_id' => $admin->getKey(),
            ]);

            AffiliateCommission::query()
                ->where('payout_request_id', $request->id)
                ->update([
                    'status' => AffiliateCommission::STATUS_PAID,
                    'paid_at' => Carbon::now(),
                ]);

            AffiliateAdjustment::query()
                ->where('payout_request_id', $request->id)
                ->update(['status' => AffiliateAdjustment::STATUS_PAID]);
        });

        Event::dispatch(new PayoutPaid($request));
        $this->statistics->forget((int) $request->partner_user_id);
    }

    private function revertCommissionsAndAdjustments(AffiliatePayoutRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            AffiliateCommission::query()
                ->where('payout_request_id', $request->id)
                ->update([
                    'status' => AffiliateCommission::STATUS_CLOSED,
                    'payout_request_id' => null,
                ]);

            AffiliateAdjustment::query()
                ->where('payout_request_id', $request->id)
                ->update([
                    'status' => AffiliateAdjustment::STATUS_CLOSED,
                    'payout_request_id' => null,
                ]);
        });
    }
}
