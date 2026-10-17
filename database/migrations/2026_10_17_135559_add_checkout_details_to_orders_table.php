<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->uuid('checkout_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['checkout_token']);
            $table->dropColumn(['customer_email', 'customer_phone', 'checkout_token']);
        });
    }
};
