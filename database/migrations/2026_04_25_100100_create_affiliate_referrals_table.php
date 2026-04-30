<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_referrals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_user_id')->index();
            $table->unsignedBigInteger('referred_user_id')->unique();
            $table->string('code_used', 32);
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('attributed_at');
            $table->timestamps();

            $table->index(['partner_user_id', 'attributed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referrals');
    }
};
