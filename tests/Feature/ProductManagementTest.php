<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private User $admin;
    private User $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->store = Store::factory()->create();
        $this->admin = User::factory()->create(['store_id' => $this->store->id]);
        $this->karyawan = User::factory()->karyawan()->create(['store_id' => $this->store->id]);
    }

    private function payload(array $override = []): array
    {
        $category = Category::factory()->create(['store_id' => $this->store->id]);

        return array_merge([
            'code' => 'BRG-100',
            'name' => 'Beras Test',
            'category_id' => $category->id,
            'supplier_id' => null,
            'purchase_price' => 10000,
            'selling_price' => 12000,
            'unit' => 'kg',
            'pack_unit' => 'karung',
            'units_per_pack' => 25,
            'min_stock' => 10,
            'initial_stock' => 50,
        ], $override);
    }

    public function test_admin_creates_product_with_initial_stock_and_movement(): void
    {
        $this->actingAs($this->admin)
            ->post(route('products.store'), $this->payload())
            ->assertRedirect(route('products.index'));

        $product = Product::where('code', 'BRG-100')->firstOrFail();
        $this->assertSame($this->store->id, $product->store_id);
        $this->assertSame(50, $product->stock);

        $movement = StockMovement::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(StockMovementType::InitialStock, $movement->type);
        $this->assertSame(50, $movement->stock_after);
    }

    public function test_packaging_is_reset_when_pack_unit_is_empty(): void
    {
        $this->actingAs($this->admin)
            ->post(route('products.store'), $this->payload(['pack_unit' => '', 'units_per_pack' => 12]));

        $product = Product::where('code', 'BRG-100')->firstOrFail();
        $this->assertNull($product->pack_unit);
        $this->assertSame(1, $product->units_per_pack);
    }

    public function test_karyawan_can_view_but_not_manage_products(): void
    {
        $product = Product::factory()->create(['store_id' => $this->store->id]);
        $this->actingAs($this->karyawan);

        $this->get(route('products.index'))->assertOk();
        $this->get(route('products.show', $product))->assertOk();
        $this->get(route('products.create'))->assertForbidden();
        $this->post(route('products.store'), $this->payload())->assertForbidden();
        $this->get(route('products.edit', $product))->assertForbidden();
        $this->put(route('products.update', $product), $this->payload())->assertForbidden();
        $this->delete(route('products.destroy', $product))->assertForbidden();

        $this->assertDatabaseCount('products', 1);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('products.index'))->assertRedirect('/login');
    }

    public function test_products_from_other_stores_are_not_accessible(): void
    {
        $other = Store::factory()->create();
        $foreign = Product::factory()->create(['store_id' => $other->id]);
        $this->actingAs($this->admin);

        $this->get(route('products.show', $foreign))->assertNotFound();
        $this->get(route('products.edit', $foreign))->assertNotFound();
        $this->put(route('products.update', $foreign), $this->payload())->assertNotFound();
        $this->delete(route('products.destroy', $foreign))->assertNotFound();
    }

    public function test_index_lists_only_own_store_and_supports_search_and_category_filter(): void
    {
        $category = Category::factory()->create(['store_id' => $this->store->id]);
        Product::factory()->create(['store_id' => $this->store->id, 'name' => 'Beras Premium', 'category_id' => $category->id]);
        Product::factory()->create(['store_id' => $this->store->id, 'name' => 'Minyak Goreng']);
        Product::factory()->create(['name' => 'Beras Toko Lain']);

        $this->actingAs($this->admin);

        $this->get(route('products.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Products/Index')->has('products.data', 2));

        $this->get(route('products.index', ['search' => 'beras']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Beras Premium'));

        $this->get(route('products.index', ['category' => $category->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Beras Premium'));
    }

    public function test_index_is_paginated(): void
    {
        Product::factory()->count(12)->create(['store_id' => $this->store->id]);

        $this->actingAs($this->admin)->get(route('products.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 10)
                ->where('products.total', 12));
    }

    public function test_product_code_must_be_unique_per_store(): void
    {
        Product::factory()->create(['store_id' => $this->store->id, 'code' => 'BRG-100']);

        $this->actingAs($this->admin)
            ->post(route('products.store'), $this->payload())
            ->assertSessionHasErrors('code');

        $other = Store::factory()->create();
        Product::factory()->create(['store_id' => $other->id, 'code' => 'BRG-777']);

        $this->post(route('products.store'), $this->payload(['code' => 'BRG-777']))
            ->assertSessionHasNoErrors();
    }

    public function test_deleted_product_code_cannot_be_reused(): void
    {
        $product = Product::factory()->create(['store_id' => $this->store->id, 'code' => 'BRG-100']);
        $product->delete();

        $this->actingAs($this->admin)
            ->post(route('products.store'), $this->payload())
            ->assertSessionHasErrors('code');
    }

    public function test_category_and_supplier_must_belong_to_the_same_store(): void
    {
        $other = Store::factory()->create();
        $foreignCategory = Category::factory()->create(['store_id' => $other->id]);

        $this->actingAs($this->admin)
            ->post(route('products.store'), $this->payload(['category_id' => $foreignCategory->id]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_numeric_fields_are_validated(): void
    {
        $this->actingAs($this->admin)
            ->post(route('products.store'), $this->payload([
                'purchase_price' => -1,
                'units_per_pack' => 0,
                'initial_stock' => -5,
            ]))
            ->assertSessionHasErrors(['purchase_price', 'units_per_pack', 'initial_stock']);
    }

    public function test_photo_upload_is_validated_and_stored(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('products.store'), $this->payload(['photo' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors('photo');

        $this->post(route('products.store'), $this->payload(['photo' => UploadedFile::fake()->image('big.jpg')->size(3000)]))
            ->assertSessionHasErrors('photo');

        $this->post(route('products.store'), $this->payload(['photo' => UploadedFile::fake()->image('beras.jpg')]))
            ->assertSessionHasNoErrors();

        $product = Product::where('code', 'BRG-100')->firstOrFail();
        Storage::disk('public')->assertExists($product->photo);
    }

    public function test_update_changes_data_but_never_stock(): void
    {
        $product = Product::factory()->create(['store_id' => $this->store->id, 'code' => 'BRG-200', 'stock' => 40]);

        $this->actingAs($this->admin)
            ->put(route('products.update', $product), $this->payload(['code' => 'BRG-200', 'name' => 'Nama Baru', 'stock' => 999]))
            ->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame('Nama Baru', $product->name);
        $this->assertSame(40, $product->stock);
    }

    public function test_replacing_and_removing_photo_deletes_old_file(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('products.store'), $this->payload(['photo' => UploadedFile::fake()->image('a.jpg')]));
        $product = Product::where('code', 'BRG-100')->firstOrFail();
        $oldPath = $product->photo;

        $this->put(route('products.update', $product), $this->payload(['photo' => UploadedFile::fake()->image('b.jpg')]));
        $product->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($product->photo);

        $currentPath = $product->photo;
        $this->put(route('products.update', $product), $this->payload(['remove_photo' => true]));
        $product->refresh();
        $this->assertNull($product->photo);
        Storage::disk('public')->assertMissing($currentPath);
    }

    public function test_delete_is_soft_delete(): void
    {
        $product = Product::factory()->create(['store_id' => $this->store->id]);

        $this->actingAs($this->admin)
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
