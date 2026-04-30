<?php

namespace A2ZWeb\Affiliate\Services;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StatementNumberGenerator
{
    /**
     * Returns the next sequential statement number for the given prefix and year.
     * Safe under concurrency: takes a row-level lock on the latest matching row
     * within a transaction. Caller MUST be inside a DB::transaction().
     *
     * Format: {prefix}-{year}-{nnnn} (e.g. ACS-UK-2026-0001).
     */
    public function next(string $prefix, ?Carbon $reference = null): string
    {
        $year = ($reference ?? Carbon::now())->year;
        $pattern = $prefix.'-'.$year.'-%';

        $latest = AffiliateCommissionStatement::query()
            ->where('statement_number', 'like', $pattern)
            ->orderByDesc('statement_number')
            ->lockForUpdate()
            ->value('statement_number');

        $next = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $m) === 1) {
            $next = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $next);
    }

    /**
     * Wrapper for callers outside an existing transaction. Wraps in its own.
     */
    public function nextInTransaction(string $prefix, ?Carbon $reference = null): string
    {
        return DB::transaction(fn (): string => $this->next($prefix, $reference));
    }
}
