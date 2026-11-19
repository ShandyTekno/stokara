<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_sees_data_from_own_store(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $productA = Product::factory()->create(['store_id' => $storeA->id]);
        $productB = Product::factory()->create(['store_id' => $storeB->id]);
        $userA = User::factory()->create(['store_id' => $storeA->id]);

        $this->actingAs($userA);

        $this->assertSame([$productA->id], Product::pluck('id')->all());
        $this->assertNull(Product::find($productB->id));
        $this->assertCount(1, Category::all());
        $this->assertCount(1, Supplier::all());
    }

    public function test_new_records_are_assigned_to_the_logged_in_users_store(): void
    {
        $store = Store::factory()->create();
        $this->actingAs(User::factory()->create(['store_id' => $store->id]));

        $category = Category::create(['name' => 'Minuman']);

        $this->assertSame($store->id, $category->store_id);
    }

    public function test_store_id_cannot_be_mass_assigned(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $this->actingAs(User::factory()->create(['store_id' => $storeA->id]));

        $category = Category::create(['name' => 'Minuman', 'store_id' => $storeB->id]);

        $this->assertSame($storeA->id, $category->store_id);
    }
}
