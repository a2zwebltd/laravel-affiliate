<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_partners', function (Blueprint $table): void {
            $table->string('billing_full_name', 191)->nullable()->after('user_id');
            $table->text('billing_address')->nullable()->after('billing_full_name');
            $table->string('country_of_tax_residence', 2)->nullable()->after('billing_address');
            $table->string('contact_email', 191)->nullable()->after('country_of_tax_residence');
            $table->string('contact_phone', 64)->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_partners', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_full_name',
                'billing_address',
                'country_of_tax_residence',
                'contact_email',
                'contact_phone',
            ]);
        });
    }
};
