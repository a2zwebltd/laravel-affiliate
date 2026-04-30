<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commission_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('statement_id')->index('affiliate_statement_lines_statement_id_index');
            $table->date('transaction_date');
            $table->string('customer_reference', 64);
            $table->string('subscription_or_invoice_reference', 191)->nullable();
            $table->decimal('gross_amount', 15, 4);
            $table->decimal('commission_rate', 5, 4);
            $table->decimal('line_commission', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commission_statement_lines');
    }
};
