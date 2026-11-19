<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $purchase = fake()->numberBetween(2, 80) * 1000;

        return [
            'store_id' => Store::factory(),
            // Kategori dan pemasok dibuat di toko yang sama dengan produk.
            'category_id' => fn (array $attrs) => Category::factory()
                ->create(['store_id' => $attrs['store_id']])->id,
            'supplier_id' => fn (array $attrs) => Supplier::factory()
                ->create(['store_id' => $attrs['store_id']])->id,
            'code' => strtoupper(fake()->unique()->bothify('BRG-####')),
            'name' => fake()->unique()->words(2, true),
            'purchase_price' => $purchase,
            'selling_price' => $purchase * 1.2,
            'stock' => fake()->numberBetween(20, 100),
            'unit' => 'pcs',
            'pack_unit' => null,
            'units_per_pack' => 1,
            'min_stock' => 10,
        ];
    }

    public function menipis(): static
    {
        return $this->state(fn () => ['stock' => 5, 'min_stock' => 10]);
    }

    public function habis(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }
}
