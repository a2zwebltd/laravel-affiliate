<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Contracts\ReferredUserInfoResolver;
use A2ZWeb\Affiliate\Models\AffiliateAdjustment;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PartnerStatistics
{
    public function __construct(
        private readonly ReferredUserInfoResolver $infoResolver,
    ) {}

    /**
     * @return array{
     *   total_earned_cents:int,
     *   last_month_earned_cents:int,
     *   available_to_request_cents:int,
     *   active_paying_referrals:int,
     *   total_referrals:int,
     *   monthly:array<int, array{year:int,month:int,gross_cents:int,paying_users:int}>,
     *   referrals:array<int, array{user_id:int,display_name:string,is_paying:bool,plan:?string,attributed_at:string,gross_last_12mo_cents:int}>,
     * }
     */
    public function for(int $partnerUserId): array
    {
        $key = $this->cacheKey($partnerUserId);
        $ttl = (int) config('affiliate.cache_ttl_seconds', 900);

        return Cache::remember($key, $ttl, fn () => $this->compute($partnerUserId));
    }

    public function forget(int $partnerUserId): void
    {
        Cache::forget($this->cacheKey($partnerUserId));
    }

    private function cacheKey(int $partnerUserId): string
    {
        return 'affiliate:partner:'.$partnerUserId.':stats:v1';
    }

    /**
     * Sum **commission impact** of adjustments per (year-month) for a given partner.
     * Adjustments are entered as deltas to the base (source revenue); the partner's
     * commission moves by `signed_base × commission_rate_bp / 10000`.
     *
     * @param  array<int, string>  $statuses
     * @return array<string, int> ["{year}-{month}" => signed_commission_cents]
     */
    private function adjustmentCommissionByMonth(int $partnerUserId, array $statuses): array
    {
        return AffiliateAdjustment::query()
            ->where('partner_user_id', $partnerUserId)
            ->whereIn('status', $statuses)
            ->get(['period_year', 'period_month', 'type', 'amount_cents', 'commission_rate_bp'])
            ->groupBy(fn (AffiliateAdjustment $a): string => $a->period_year.'-'.$a->period_month)
            ->map(fn ($group): int => (int) $group->reduce(
                fn (int $sum, AffiliateAdjustment $a): int => $sum + $a->commissionAmountCents(),
                0,
            ))
            ->all();
    }

    private function compute(int $partnerUserId): array
    {
        $countableStatuses = [
            AffiliateCommission::STATUS_CLOSED,
            AffiliateCommission::STATUS_REQUESTED,
            AffiliateCommission::STATUS_PAID,
        ];
        $closedScope = fn ($q) => $q->whereIn('status', $countableStatuses);

        $adjustmentsByMonth = $this->adjustmentCommissionByMonth($partnerUserId, $countableStatuses);
        $closedAdjustmentsByMonth = $this->adjustmentCommissionByMonth($partnerUserId, [AffiliateAdjustment::STATUS_CLOSED]);

        $commissionsByMonth = AffiliateCommission::query()
            ->where('partner_user_id', $partnerUserId)
            ->tap($closedScope)
            ->selectRaw('period_year, period_month, SUM(commission_amount_cents) AS gross_cents')
            ->groupBy('period_year', 'period_month')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $row->period_year.'-'.$row->period_month => (int) $row->gross_cents,
            ])
            ->all();

        $closedCommissionsByMonth = AffiliateCommission::query()
            ->where('partner_user_id', $partnerUserId)
            ->where('status', AffiliateCommission::STATUS_CLOSED)
            ->selectRaw('period_year, period_month, SUM(commission_amount_cents) AS gross_cents')
            ->groupBy('period_year', 'period_month')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $row->period_year.'-'.$row->period_month => (int) $row->gross_cents,
            ])
            ->all();

        $netForMonth = static function (array $grossMap, array $adjMap): array {
            $keys = array_unique(array_merge(array_keys($grossMap), array_keys($adjMap)));
            $out = [];
            foreach ($keys as $key) {
                $out[$key] = (int) (($grossMap[$key] ?? 0) + ($adjMap[$key] ?? 0));
            }

            return $out;
        };

        $netByMonth = $netForMonth($commissionsByMonth, $adjustmentsByMonth);
        $closedNetByMonth = $netForMonth($closedCommissionsByMonth, $closedAdjustmentsByMonth);

        $lastClosed = Carbon::now()->subMonthNoOverflow();
        $lastMonthEarnedCents = (int) ($netByMonth[$lastClosed->year.'-'.$lastClosed->month] ?? 0);

        $availableToRequestCents = (int) array_sum($closedNetByMonth);

        $referrals = AffiliateReferral::query()
            ->where('partner_user_id', $partnerUserId)
            ->orderByDesc('attributed_at')
            ->get();

        $totalReferrals = $referrals->count();
        $activePaying = 0;

        // Skeleton: last 12 closed months. Always render these even if zero.
        $monthlyMap = [];
        for ($i = 0; $i < 12; $i++) {
            $d = Carbon::now()->subMonthsNoOverflow($i + 1);
            $monthlyMap[$d->year.'-'.$d->month] = [
                'year' => $d->year,
                'month' => $d->month,
                'gross_cents' => $netByMonth[$d->year.'-'.$d->month] ?? 0,
                'paying_users' => 0,
            ];
        }

        // Extend with any older months that have non-zero data (commissions or adjustments)
        // so the chart sum reconciles with `total_earned_cents` and nothing is hidden.
        foreach ($netByMonth as $key => $cents) {
            if (! isset($monthlyMap[$key]) && $cents !== 0) {
                [$y, $m] = array_map('intval', explode('-', $key));
                $monthlyMap[$key] = [
                    'year' => $y,
                    'month' => $m,
                    'gross_cents' => $cents,
                    'paying_users' => 0,
                ];
            }
        }

        $totalEarnedCents = (int) array_sum(array_column($monthlyMap, 'gross_cents'));

        $commissions = AffiliateCommission::query()
            ->where('partner_user_id', $partnerUserId)
            ->tap($closedScope)
            ->where('period_year', '>=', Carbon::now()->subYear()->year)
            ->get(['referred_user_id', 'period_year', 'period_month']);

        $payingByMonth = [];
        foreach ($commissions as $c) {
            $key = $c->period_year.'-'.$c->period_month;
            if (! isset($monthlyMap[$key])) {
                continue;
            }
            if (! isset($payingByMonth[$key])) {
                $payingByMonth[$key] = [];
            }
            $payingByMonth[$key][$c->referred_user_id] = true;
        }
        foreach ($payingByMonth as $key => $set) {
            $monthlyMap[$key]['paying_users'] = count($set);
        }

        $referralRows = [];
        $perUserGross = AffiliateCommission::query()
            ->where('partner_user_id', $partnerUserId)
            ->tap($closedScope)
            ->where('period_year', '>=', Carbon::now()->subYear()->year)
            ->selectRaw('referred_user_id, SUM(commission_amount_cents) AS gross_cents')
            ->groupBy('referred_user_id')
            ->pluck('gross_cents', 'referred_user_id')
            ->all();

        foreach ($referrals as $referral) {
            $info = $this->infoResolver->infoFor((int) $referral->referred_user_id);

            if (! empty($info['is_paying'])) {
                $activePaying++;
            }

            $referralRows[] = [
                'user_id' => (int) $referral->referred_user_id,
                'display_name' => $info['display_name'] ?? ('User #'.$referral->referred_user_id),
                'is_paying' => (bool) ($info['is_paying'] ?? false),
                'plan' => $info['plan'] ?? null,
                'attributed_at' => $referral->attributed_at?->toIso8601String() ?? '',
                'gross_last_12mo_cents' => (int) ($perUserGross[$referral->referred_user_id] ?? 0),
            ];
        }

        $hasPending = AffiliatePayoutRequest::query()
            ->where('partner_user_id', $partnerUserId)
            ->whereIn('status', [
                AffiliatePayoutRequest::STATUS_PENDING,
                AffiliatePayoutRequest::STATUS_APPROVED,
            ])
            ->exists();

        // Sort monthly oldest → newest by (year, month) — covers older periods we appended.
        uasort($monthlyMap, fn (array $a, array $b): int => [$a['year'], $a['month']] <=> [$b['year'], $b['month']]);

        return [
            'total_earned_cents' => $totalEarnedCents,
            'last_month_earned_cents' => $lastMonthEarnedCents,
            'available_to_request_cents' => $availableToRequestCents,
            'has_pending_payout_request' => $hasPending,
            'active_paying_referrals' => $activePaying,
            'total_referrals' => $totalReferrals,
            'min_payout_cents' => (int) config('affiliate.min_payout_cents', 5000),
            'monthly' => array_values($monthlyMap),
            'referrals' => $referralRows,
            'adjustments' => $this->adjustmentsList($partnerUserId),
        ];
    }

    /**
     * Partner-visible list of adjustments. Reason is internal-only and excluded.
     *
     * @return array<int, array{year:int,month:int,type:string,base_cents:int,commission_cents:int,status:string,created_at:string}>
     */
    private function adjustmentsList(int $partnerUserId): array
    {
        return AffiliateAdjustment::query()
            ->where('partner_user_id', $partnerUserId)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->get(['period_year', 'period_month', 'type', 'amount_cents', 'commission_rate_bp', 'status', 'created_at'])
            ->map(fn (AffiliateAdjustment $a): array => [
                'year' => (int) $a->period_year,
                'month' => (int) $a->period_month,
                'type' => (string) $a->type,
                'base_cents' => $a->signedAmountCents(),
                'commission_cents' => $a->commissionAmountCents(),
                'status' => (string) $a->status,
                'created_at' => $a->created_at?->toIso8601String() ?? '',
            ])
            ->all();
    }
}
