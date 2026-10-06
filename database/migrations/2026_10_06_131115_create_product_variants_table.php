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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('team_id');
            $table->string('name');
            $table->string('sku', 100);
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->foreign(['product_id', 'team_id'])->references(['id', 'team_id'])->on('products')->cascadeOnDelete();
            $table->unique(['team_id', 'sku']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
