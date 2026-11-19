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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->integer('quantity_change');             // positif = masuk, negatif = keluar
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->nullableMorphs('reference');            // transaksi asal (stock_in, stock_out, sale)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'product_id', 'created_at']);
            $table->index(['store_id', 'type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
