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
        Schema::table('branches', function (Blueprint $table) {
            $table->unique(['id', 'team_id']);
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['id', 'team_id']);
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedInteger('quantity')->default(0);
            $table->unique(['branch_id', 'product_variant_id']);
            $table->foreign(['branch_id', 'team_id'])->references(['id', 'team_id'])->on('branches')->cascadeOnDelete();
            $table->foreign(['product_variant_id', 'team_id'])->references(['id', 'team_id'])->on('product_variants')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropUnique(['id', 'team_id']));
        Schema::table('branches', fn (Blueprint $table) => $table->dropUnique(['id', 'team_id']));
    }
};
