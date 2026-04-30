<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_partners', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('code', 32)->unique();
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending')->index();

            $table->enum('payout_method', ['paypal', 'bank_transfer'])->nullable();
            $table->string('paypal_email')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->string('bank_iban', 64)->nullable();
            $table->string('bank_swift', 32)->nullable();
            $table->string('bank_address')->nullable();

            $table->boolean('is_company')->default(false);
            $table->string('company_name')->nullable();
            $table->string('tax_id', 64)->nullable();

            $table->timestamp('applied_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->date('program_joined_at')->nullable()->index();

            $table->string('accepted_general_terms_version', 32)->nullable();
            $table->string('accepted_affiliate_terms_version', 32)->nullable();
            $table->timestamp('accepted_terms_at')->nullable();
            $table->string('accepted_terms_ip', 64)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_partners');
    }
};
