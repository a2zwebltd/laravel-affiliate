<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_payout_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'cancelled'])->default('pending')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('gross_amount_cents');
            $table->bigInteger('adjustments_amount_cents')->default(0);
            $table->unsignedBigInteger('net_amount_cents');
            $table->string('currency', 3)->default('usd');
            $table->json('payout_method_snapshot');
            $table->timestamp('requested_at');
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('payment_reference', 191)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_payout_requests');
    }
};
