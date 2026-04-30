<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commission_statements', function (Blueprint $table): void {
            $table->id();
            $table->string('statement_number', 64)->unique('affiliate_statements_statement_number_unique');

            // Issuing entity snapshot (frozen at draft creation, never updated)
            $table->string('issuing_entity', 64);
            $table->string('issuing_entity_legal_name', 191);
            $table->string('issuing_entity_company_number', 64)->nullable();
            $table->string('issuing_entity_company_number_label', 64)->nullable();
            $table->text('issuing_entity_address')->nullable();
            $table->string('issuing_entity_country', 64)->nullable();
            $table->text('issuing_entity_tax_status_note')->nullable();
            $table->string('issuing_entity_statement_prefix', 32);

            // Affiliate side snapshot (frozen at draft creation): legal name, address, country of tax residence,
            // contact details, payout method etc. — so future profile edits do NOT mutate historical statements.
            $table->json('affiliate_snapshot')->nullable();

            $table->unsignedBigInteger('partner_user_id')->index('affiliate_statements_partner_user_id_index');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('currency', 3)->default('usd');

            $table->decimal('gross_revenue_total', 15, 4)->default(0);
            $table->decimal('commission_rate', 5, 4);
            $table->decimal('commission_amount', 15, 4)->default(0);

            $table->enum('payment_method', ['bank_transfer', 'wise', 'paypal', 'other'])->nullable();
            $table->string('payment_reference', 191)->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('payment_status', ['draft', 'issued', 'paid', 'cancelled'])->default('draft')->index('affiliate_statements_payment_status_index');

            $table->string('pdf_disk', 32)->nullable();
            $table->string('pdf_path', 512)->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('sent_to_affiliate_at')->nullable();

            $table->unsignedBigInteger('payout_request_id')->nullable()->index('affiliate_statements_payout_request_id_index');

            $table->timestamps();

            $table->unique(['partner_user_id', 'period_start', 'period_end'], 'affiliate_statements_unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commission_statements');
    }
};
