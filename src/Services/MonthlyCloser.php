<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Contracts\RevenueResolver;
use A2ZWeb\Affiliate\Events\MonthClosed;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Models\AffiliateReferral;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class MonthlyCloser
{
    public function __construct(
        private readonly RevenueResolver $revenueResolver,
        private readonly CommissionCalculator $calculator,
        private readonly PartnerStatistics $statistics,
    ) {}

    public function closeMonth(int $year, int $month, ?int $onlyPartnerUserId = null): int
    {
        $this->guardMonth($year, $month);

        $partners = AffiliatePartner::query()
            ->where('status', AffiliatePartner::STATUS_APPROVED)
            ->when($onlyPartnerUserId, fn ($q) => $q->where('user_id', $onlyPartnerUserId))
            ->get();

        $touched = 0;

        foreach ($partners as $partner) {
            if (! $this->partnerEligibleForMonth($partner, $year, $month)) {
                continue;
            }

            $rateBp = $partner->effectiveRevenueShareBp();

            $referrals = AffiliateReferral::query()
                ->where('partner_user_id', $partner->user_id)
                ->get();

            foreach ($referrals as $referral) {
                if (! $this->referralWithinWindow($referral, $year, $month)) {
                    continue;
                }

                $sourceCents = $this->revenueResolver->revenueForUserInMonth(
                    (int) $referral->referred_user_id,
                    $year,
                    $month
                );

                if ($sourceCents <= 0) {
                    continue;
                }

                $commissionCents = $this->calculator->commissionCents($sourceCents, $rateBp);

                $row = AffiliateCommission::query()
                    ->where('partner_user_id', $partner->user_id)
                    ->where('referred_user_id', $referral->referred_user_id)
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->first();

                if ($row && in_array($row->status, [AffiliateCommission::STATUS_REQUESTED, AffiliateCommission::STATUS_PAID], true)) {
                    continue;
                }

                AffiliateCommission::query()->updateOrCreate([
                    'partner_user_id' => $partner->user_id,
                    'referred_user_id' => $referral->referred_user_id,
                    'period_year' => $year,
                    'period_month' => $month,
                ], [
                    'source_amount_cents' => $sourceCents,
                    'commission_amount_cents' => $commissionCents,
                    'commission_rate_bp' => $rateBp,
                    'currency' => config('affiliate.currency', 'usd'),
                    'status' => AffiliateCommission::STATUS_CLOSED,
                    'closed_at' => Carbon::now(),
                ]);

                $touched++;
            }

            $this->statistics->forget((int) $partner->user_id);
        }

        Event::dispatch(new MonthClosed($year, $month, $touched));

        return $touched;
    }

    public function recalcPartner(int $partnerUserId): int
    {
        $partner = AffiliatePartner::query()->where('user_id', $partnerUserId)->firstOrFail();

        $start = $partner->program_joined_at
            ? Carbon::parse($partner->program_joined_at)->startOfMonth()
            : Carbon::parse($partner->decided_at ?? $partner->created_at)->startOfMonth();

        $end = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        $totalTouched = 0;
        $cursor = $start->copy();

        DB::transaction(function () use ($partner, $start) {
            AffiliateCommission::query()
                ->where('partner_user_id', $partner->user_id)
                ->where('status', AffiliateCommission::STATUS_CLOSED)
                ->whereRaw('(period_year * 100 + period_month) < ?', [$start->year * 100 + $start->month])
                ->delete();
        });

        while ($cursor->lessThanOrEqualTo($end)) {
            $totalTouched += $this->closeMonth($cursor->year, $cursor->month, (int) $partner->user_id);
            $cursor->addMonthNoOverflow();
        }

        Cache::forget('affiliate:partner:'.$partner->user_id.':stats:v1');

        return $totalTouched;
    }

    private function partnerEligibleForMonth(AffiliatePartner $partner, int $year, int $month): bool
    {
        $joinedAt = $partner->program_joined_at
            ?? $partner->decided_at
            ?? $partner->created_at;

        $monthFloor = Carbon::create($year, $month, 1)->endOfMonth();

        return Carbon::parse($joinedAt)->lessThanOrEqualTo($monthFloor);
    }

    private function referralWithinWindow(AffiliateReferral $referral, int $year, int $month): bool
    {
        $mode = config('affiliate.commission_window.mode', 'lifetime');
        if ($mode === 'lifetime') {
            return true;
        }

        $months = (int) config('affiliate.commission_window.months', 12);
        $cutoff = Carbon::parse($referral->attributed_at)->addMonthsNoOverflow($months);
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();

        return $monthEnd->lessThanOrEqualTo($cutoff);
    }

    private function guardMonth(int $year, int $month): void
    {
        $now = Carbon::now();
        $target = Carbon::create($year, $month, 1)->endOfMonth();

        if ($target->greaterThanOrEqualTo($now->startOfMonth())) {
            throw new InvalidArgumentException('Cannot close current or future month — affiliate windows operate on closed past months only.');
        }
    }
}
