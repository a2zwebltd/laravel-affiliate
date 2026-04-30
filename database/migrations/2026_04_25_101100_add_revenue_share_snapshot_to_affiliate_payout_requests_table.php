<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail: snapshot the partner's commission rate at payout-request time
     * so a later rate change doesn't retroactively distort historical payouts.
     * Individual `affiliate_commissions` rows already snapshot their own
     * `commission_rate_bp`; this column captures the rate that applied to the
     * partner overall when the payout was requested.
     */
    public function up(): void
    {
        Schema::table('affiliate_payout_requests', function (Blueprint $table): void {
            $table->unsignedSmallInteger('revenue_share_bp_snapshot')->nullable()->after('payout_method_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_payout_requests', function (Blueprint $table): void {
            $table->dropColumn('revenue_share_bp_snapshot');
        });
    }
};
