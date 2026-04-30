<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_payout_requests', function (Blueprint $table): void {
            $table->string('invoice_disk', 32)->nullable()->after('payout_method_snapshot');
            $table->string('invoice_file_path', 512)->nullable()->after('invoice_disk');
            $table->string('invoice_original_filename', 191)->nullable()->after('invoice_file_path');
            $table->string('purchase_order_id', 191)->nullable()->after('invoice_original_filename');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_payout_requests', function (Blueprint $table): void {
            $table->dropColumn(['invoice_disk', 'invoice_file_path', 'invoice_original_filename', 'purchase_order_id']);
        });
    }
};
