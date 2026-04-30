<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->unsignedBigInteger('referred_user_id')->index();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedBigInteger('source_amount_cents')->default(0);
            $table->unsignedBigInteger('commission_amount_cents')->default(0);
            $table->unsignedSmallInteger('commission_rate_bp');
            $table->string('currency', 3)->default('usd');
            $table->enum('status', ['closed', 'requested', 'paid', 'reversed'])->default('closed')->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('payout_request_id')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['partner_user_id', 'referred_user_id', 'period_year', 'period_month'],
                'affiliate_commissions_unique'
            );
            $table->index(['partner_user_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
