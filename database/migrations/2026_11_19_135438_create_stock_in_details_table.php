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
        Schema::create('stock_in_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_in_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('input_quantity');              // jumlah sesuai yang diketik (mis. 3 dus)
            $table->boolean('is_package')->default(false);          // true jika input dalam kemasan
            $table->unsignedInteger('units_per_pack')->default(1);  // snapshot isi kemasan saat transaksi
            $table->unsignedInteger('quantity');                    // jumlah dalam satuan terkecil (menambah stok)
            $table->decimal('unit_cost', 15, 2);                    // harga beli per satuan terkecil
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index(['store_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_in_details');
    }
};
