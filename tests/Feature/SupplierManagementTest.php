<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockInDetail;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
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

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Pak Haji Rahmat',
            'company_name' => 'UD Rahmat Jaya',
            'phone' => '0812-3456-7890',
            'address' => 'Pasar Induk',
        ], $override);
    }

    public function test_admin_can_create_supplier_in_own_store(): void
    {
        $this->actingAs($this->admin)
            ->post(route('suppliers.store'), $this->payload())
            ->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::where('name', 'Pak Haji Rahmat')->firstOrFail();
        $this->assertSame($this->store->id, $supplier->store_id);
    }

    public function test_company_name_is_optional_but_name_and_phone_format_are_validated(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('suppliers.store'), $this->payload(['company_name' => null]))
            ->assertSessionHasNoErrors();

        $this->post(route('suppliers.store'), $this->payload(['name' => '', 'phone' => 'abc']))
            ->assertSessionHasErrors(['name', 'phone']);
    }

    public function test_karyawan_is_forbidden(): void
    {
        $supplier = Supplier::factory()->create(['store_id' => $this->store->id]);
        $this->actingAs($this->karyawan);

        $this->get(route('suppliers.index'))->assertForbidden();
        $this->get(route('suppliers.create'))->assertForbidden();
        $this->post(route('suppliers.store'), $this->payload())->assertForbidden();
        $this->get(route('suppliers.show', $supplier))->assertForbidden();
        $this->put(route('suppliers.update', $supplier), $this->payload())->assertForbidden();
        $this->delete(route('suppliers.destroy', $supplier))->assertForbidden();
    }

    public function test_suppliers_from_other_stores_are_not_accessible(): void
    {
        $foreign = Supplier::factory()->create();
        $this->actingAs($this->admin);

        $this->get(route('suppliers.show', $foreign))->assertNotFound();
        $this->get(route('suppliers.edit', $foreign))->assertNotFound();
        $this->put(route('suppliers.update', $foreign), $this->payload())->assertNotFound();
        $this->delete(route('suppliers.destroy', $foreign))->assertNotFound();
    }

    public function test_index_lists_own_suppliers_and_supports_search(): void
    {
        Supplier::factory()->create(['store_id' => $this->store->id, 'name' => 'Pak Haji', 'company_name' => null]);
        Supplier::factory()->create(['store_id' => $this->store->id, 'name' => 'Ibu Sinta', 'company_name' => null]);
        Supplier::factory()->create(['name' => 'Pak Haji Toko Lain']);

        $this->actingAs($this->admin);

        $this->get(route('suppliers.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Suppliers/Index')->has('suppliers.data', 2));

        $this->get(route('suppliers.index', ['search' => 'sinta']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('suppliers.data', 1)
                ->where('suppliers.data.0.name', 'Ibu Sinta'));
    }

    public function test_show_displays_procurement_history_and_summary(): void
    {
        $supplier = Supplier::factory()->create(['store_id' => $this->store->id]);
        $product = Product::factory()->create(['store_id' => $this->store->id, 'supplier_id' => $supplier->id]);

        $this->actingAs($this->admin);

        $stockIn = StockIn::create([
            'user_id' => $this->admin->id,
            'supplier_id' => $supplier->id,
            'transaction_number' => 'SI-0001',
            'transaction_date' => today(),
            'total_cost' => 50000,
        ]);
        StockInDetail::create([
            'stock_in_id' => $stockIn->id,
            'product_id' => $product->id,
            'input_quantity' => 5,
            'is_package' => false,
            'units_per_pack' => 1,
            'quantity' => 5,
            'unit_cost' => 10000,
            'subtotal' => 50000,
        ]);

        $this->get(route('suppliers.show', ['supplier' => $supplier->id, 'tab' => 'pengadaan']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Suppliers/Show')
                ->where('tab', 'pengadaan')
                ->where('summary.total_transactions', 1)
                ->where('summary.total_spent', fn ($v) => (float) $v === 50000.0)
                ->has('history.data', 1)
                ->where('history.data.0.transaction_number', 'SI-0001')
                ->where('history.data.0.items', 1)
                ->has('products', 1));
    }

    public function test_supplier_with_procurement_history_cannot_be_deleted(): void
    {
        $supplier = Supplier::factory()->create(['store_id' => $this->store->id]);
        $this->actingAs($this->admin);

        StockIn::create([
            'supplier_id' => $supplier->id,
            'transaction_number' => 'SI-0001',
            'transaction_date' => today(),
            'total_cost' => 1000,
        ]);

        $this->delete(route('suppliers.destroy', $supplier))->assertSessionHas('error');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_without_history_can_be_deleted_and_products_become_unlinked(): void
    {
        $supplier = Supplier::factory()->create(['store_id' => $this->store->id]);
        $product = Product::factory()->create(['store_id' => $this->store->id, 'supplier_id' => $supplier->id]);

        $this->actingAs($this->admin)
            ->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
        $this->assertNull($product->fresh()->supplier_id);
    }
}
