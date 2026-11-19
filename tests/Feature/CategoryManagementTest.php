<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private User $admin;
    private User $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create();
        $this->admin = User::factory()->create(['store_id' => $this->store->id]);
        $this->karyawan = User::factory()->karyawan()->create(['store_id' => $this->store->id]);
    }

    public function test_admin_can_create_category_in_own_store(): void
    {
        $this->actingAs($this->admin)
            ->post(route('categories.store'), ['name' => 'Minuman', 'description' => 'Semua minuman'])
            ->assertRedirect(route('categories.index'));

        $category = Category::where('name', 'Minuman')->firstOrFail();
        $this->assertSame($this->store->id, $category->store_id);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('categories.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_name_is_unique_per_store_only(): void
    {
        Category::factory()->create(['store_id' => $this->store->id, 'name' => 'Minuman']);
        $other = Store::factory()->create();
        Category::factory()->create(['store_id' => $other->id, 'name' => 'Snack']);

        $this->actingAs($this->admin);

        $this->post(route('categories.store'), ['name' => 'Minuman'])->assertSessionHasErrors('name');
        $this->post(route('categories.store'), ['name' => 'Snack'])->assertSessionHasNoErrors();
    }

    public function test_admin_can_update_category_keeping_its_own_name(): void
    {
        $category = Category::factory()->create(['store_id' => $this->store->id, 'name' => 'Minuman']);

        $this->actingAs($this->admin)
            ->put(route('categories.update', $category), ['name' => 'Minuman', 'description' => 'Baru'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'));

        $this->assertSame('Baru', $category->fresh()->description);
    }

    public function test_karyawan_is_forbidden(): void
    {
        $category = Category::factory()->create(['store_id' => $this->store->id]);
        $this->actingAs($this->karyawan);

        $this->get(route('categories.index'))->assertForbidden();
        $this->post(route('categories.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('categories.update', $category), ['name' => 'Y'])->assertForbidden();
        $this->delete(route('categories.destroy', $category))->assertForbidden();
    }

    public function test_categories_from_other_stores_are_not_accessible(): void
    {
        $foreign = Category::factory()->create(); // toko lain
        $this->actingAs($this->admin);

        $this->put(route('categories.update', $foreign), ['name' => 'X'])->assertNotFound();
        $this->delete(route('categories.destroy', $foreign))->assertNotFound();
    }

    public function test_index_shows_own_categories_with_product_counts_and_search(): void
    {
        $drink = Category::factory()->create(['store_id' => $this->store->id, 'name' => 'Minuman']);
        Category::factory()->create(['store_id' => $this->store->id, 'name' => 'Snack']);
        Category::factory()->create(['name' => 'Minuman Toko Lain']);
        Product::factory()->count(2)->create(['store_id' => $this->store->id, 'category_id' => $drink->id]);

        $this->actingAs($this->admin);

        $this->get(route('categories.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Categories/Index')->has('categories.data', 2));

        $this->get(route('categories.index', ['search' => 'minum']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories.data', 1)
                ->where('categories.data.0.products_count', 2));
    }

    public function test_deleting_category_keeps_products_without_category(): void
    {
        $category = Category::factory()->create(['store_id' => $this->store->id]);
        $product = Product::factory()->create(['store_id' => $this->store->id, 'category_id' => $category->id]);

        $this->actingAs($this->admin)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertNull($product->fresh()->category_id);
    }
}
