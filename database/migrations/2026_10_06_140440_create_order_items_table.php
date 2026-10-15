<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained();
            $table->string('product_name');
            $table->string('variant_name');
            $table->string('sku', 100);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unique(['order_id', 'product_variant_id']);
            $table->timestamps();
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('order_id'));
        Schema::dropIfExists('order_items');
    }
};
