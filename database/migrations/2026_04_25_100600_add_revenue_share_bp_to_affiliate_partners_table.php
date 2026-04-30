<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_partners', function (Blueprint $table): void {
            $table->unsignedSmallInteger('revenue_share_bp')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_partners', function (Blueprint $table): void {
            $table->dropColumn('revenue_share_bp');
        });
    }
};
