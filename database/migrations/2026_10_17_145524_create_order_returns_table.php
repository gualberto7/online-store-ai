<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unique(['id', 'order_id']);
        });
        Schema::create('order_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_item_id');
            $table->foreign(['order_item_id', 'order_id'])->references(['id', 'order_id'])->on('order_items')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->boolean('restock');
            $table->bigInteger('amount_cents');
            $table->string('payment_method', 20);
            $table->string('payment_reference')->nullable();
            $table->string('reason', 500);
            $table->uuid('request_token');
            $table->unique(['order_id', 'request_token']);
            $table->index(['order_item_id', 'order_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_returns');
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropUnique(['id', 'order_id']);
        });
    }
};
