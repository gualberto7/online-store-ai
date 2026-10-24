<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $postgres = Schema::getConnection()->getDriverName() === 'pgsql';
        if ($postgres) {
            Schema::ensureVectorExtensionExists();
        }
        Schema::table('products', function (Blueprint $table) use ($postgres): void {
            if ($postgres) {
                $table->vector('search_embedding')->nullable();
            } else {
                $table->json('search_embedding')->nullable();
            }
            $table->string('search_embedding_profile', 64)->nullable();
            $table->string('search_content_hash', 64)->nullable();
            $table->timestamp('search_indexed_at')->nullable();
            $table->unsignedBigInteger('search_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['search_embedding', 'search_embedding_profile', 'search_content_hash', 'search_indexed_at', 'search_version']);
        });
    }
};
