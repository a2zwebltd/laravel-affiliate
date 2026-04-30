<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_adjustments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('commission_rate_bp')->after('amount_cents')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_adjustments', function (Blueprint $table): void {
            $table->dropColumn('commission_rate_bp');
        });
    }
};
