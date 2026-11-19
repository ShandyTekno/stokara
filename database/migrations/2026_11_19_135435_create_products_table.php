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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->decimal('purchase_price', 15, 2)->default(0);   // per satuan terkecil
            $table->decimal('selling_price', 15, 2)->default(0);    // per satuan terkecil
            $table->unsignedInteger('stock')->default(0);           // satuan terkecil; unsigned = tidak bisa negatif
            $table->string('unit', 20);                             // satuan terkecil (pcs, kg, butir, ...)
            $table->string('pack_unit', 20)->nullable();            // kemasan (dus, karung, tray, ...)
            $table->unsignedInteger('units_per_pack')->default(1);  // isi satu kemasan dalam satuan terkecil
            $table->unsignedInteger('min_stock')->default(0);
            $table->string('photo')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'code']);
            $table->index(['store_id', 'name']);
            $table->index(['store_id', 'category_id']);
            $table->index(['store_id', 'stock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
