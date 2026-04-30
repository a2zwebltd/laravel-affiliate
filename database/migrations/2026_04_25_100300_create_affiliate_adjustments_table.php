<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->enum('type', ['addition', 'subtraction']);
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('usd');
            $table->text('reason');
            $table->unsignedBigInteger('admin_user_id');
            $table->unsignedBigInteger('payout_request_id')->nullable()->index();
            $table->enum('status', ['closed', 'requested', 'paid'])->default('closed')->index();
            $table->timestamps();

            $table->index(['partner_user_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_adjustments');
    }
};
