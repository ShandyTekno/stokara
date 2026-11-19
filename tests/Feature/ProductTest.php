<?php

namespace Tests\Feature;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_status_follows_stock_and_minimum(): void
    {
        $product = new Product();
        $product->min_stock = 10;

        $product->stock = 0;
        $this->assertSame(StockStatus::Habis, $product->stock_status);

        $product->stock = 10;
        $this->assertSame(StockStatus::Menipis, $product->stock_status);

        $product->stock = 11;
        $this->assertSame(StockStatus::Aman, $product->stock_status);
    }

    public function test_stock_and_store_id_are_not_mass_assignable(): void
    {
        $product = new Product(['name' => 'Beras', 'stock' => 99, 'store_id' => 5]);

        $this->assertNull($product->stock);
        $this->assertNull($product->store_id);
    }

    public function test_product_code_is_unique_per_store_only(): void
    {
        $a = Store::factory()->create();
        $b = Store::factory()->create();

        Product::factory()->create(['store_id' => $a->id, 'code' => 'BRG-001']);
        Product::factory()->create(['store_id' => $b->id, 'code' => 'BRG-001']);
        $this->assertDatabaseCount('products', 2);

        $this->expectException(QueryException::class);
        Product::factory()->create(['store_id' => $a->id, 'code' => 'BRG-001']);
    }

    public function test_database_rejects_negative_stock(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Kolom unsigned hanya ditegakkan oleh MySQL/MariaDB.');
        }

        $product = Product::factory()->create(['stock' => 5]);

        $this->expectException(QueryException::class);
        DB::table('products')->where('id', $product->id)->decrement('stock', 6);
    }

    public function test_low_stock_and_out_of_stock_scopes(): void
    {
        $store = Store::factory()->create();
        Product::factory()->create(['store_id' => $store->id, 'stock' => 50, 'min_stock' => 10]);
        $low = Product::factory()->menipis()->create(['store_id' => $store->id]);
        $out = Product::factory()->habis()->create(['store_id' => $store->id]);

        $this->assertSame([$low->id], Product::lowStock()->pluck('id')->all());
        $this->assertSame([$out->id], Product::outOfStock()->pluck('id')->all());
    }
}
