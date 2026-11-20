<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private User $admin;
    private User $karyawan;
    private Product $beras;
    private Product $gula;
    private Product $minyak;
    private Product $telur;
    private Product $garam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create();
        $this->admin = User::factory()->create(['store_id' => $this->store->id]);
        $this->karyawan = User::factory()->karyawan()->create(['store_id' => $this->store->id]);

        $make = fn (string $name, int $stock) => Product::factory()->create([
            'store_id' => $this->store->id,
            'name' => $name,
            'stock' => $stock,
            'min_stock' => 10,
            'unit' => 'pcs',
            'pack_unit' => null,
            'units_per_pack' => 1,
        ]);

        $this->beras = $make('Beras', 50);   // aman
        $this->gula = $make('Gula', 20);     // aman
        $this->minyak = $make('Minyak', 5);  // menipis
        $this->telur = $make('Telur', 10);   // menipis (sama dengan minimum)
        $this->garam = $make('Garam', 0);    // habis
    }

    public function test_summary_counts_and_problem_products_come_first(): void
    {
        $this->actingAs($this->karyawan)->get(route('monitoring.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Monitoring/Index')
                ->where('tab', 'stok')
                ->where('counts.total', 5)
                ->where('counts.aman', 2)
                ->where('counts.menipis', 2)
                ->where('counts.habis', 1)
                ->has('products.data', 5)
                ->where('products.data.0.name', 'Garam')
                ->where('products.data.0.status', 'habis')
                ->where('products.data.1.name', 'Minyak')
                ->where('products.data.2.name', 'Telur')
                ->where('products.data.3.name', 'Beras')
                ->where('products.data.4.name', 'Gula')
                ->where('products.data.1.gap', -5)
                ->where('movements', null));
    }

    public function test_admin_can_open_monitoring_too(): void
    {
        $this->actingAs($this->admin)->get(route('monitoring.index'))->assertOk();
    }

    public function test_status_filter(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('monitoring.index', ['status' => 'habis']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Garam'));

        $this->get(route('monitoring.index', ['status' => 'menipis']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 2));

        $this->get(route('monitoring.index', ['status' => 'aman']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
    }

    public function test_search_and_category_filter(): void
    {
        $category = Category::factory()->create(['store_id' => $this->store->id, 'name' => 'Dapur']);
        Product::whereKey([$this->garam->id, $this->minyak->id])->update(['category_id' => $category->id]);

        $this->actingAs($this->admin);

        $this->get(route('monitoring.index', ['search' => 'gar']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Garam'));

        $this->get(route('monitoring.index', ['category' => $category->id]))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
    }

    public function test_other_stores_are_excluded(): void
    {
        $other = Store::factory()->create();
        Product::factory()->habis()->create(['store_id' => $other->id]);

        $this->actingAs($this->admin)->get(route('monitoring.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.total', 5)
                ->where('counts.habis', 1)
                ->has('products.data', 5));
    }

    public function test_history_tab_lists_movements_with_filters(): void
    {
        $service = app(StockService::class);
        $service->recordStockIn([
            'transaction_date' => today()->toDateString(),
            'items' => [['product_id' => $this->beras->id, 'quantity' => 10, 'is_package' => false, 'price' => 1000]],
        ], $this->admin);
        $service->recordStockOut([
            'transaction_date' => today()->toDateString(),
            'reason' => 'rusak',
            'items' => [['product_id' => $this->minyak->id, 'quantity' => 2]],
        ], $this->karyawan);

        $this->actingAs($this->karyawan);

        $this->get(route('monitoring.index', ['tab' => 'riwayat']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'riwayat')
                ->where('products', null)
                ->has('movements.data', 2)
                ->where('movements.data.0.type', 'Barang Keluar')
                ->where('movements.data.0.change', -2)
                ->where('movements.data.0.before', 5)
                ->where('movements.data.0.after', 3)
                ->where('movements.data.0.user', $this->karyawan->name));

        $this->get(route('monitoring.index', ['tab' => 'riwayat', 'type' => 'stock_in']))
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 1)->where('movements.data.0.product', 'Beras'));

        $this->get(route('monitoring.index', ['tab' => 'riwayat', 'search' => 'minyak']))
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 1));

        $this->get(route('monitoring.index', ['tab' => 'riwayat', 'from' => today()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 2));

        $this->get(route('monitoring.index', ['tab' => 'riwayat', 'to' => today()->subDay()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 0));
    }

    public function test_history_keeps_movements_of_deleted_products(): void
    {
        app(StockService::class)->recordStockIn([
            'transaction_date' => today()->toDateString(),
            'items' => [['product_id' => $this->beras->id, 'quantity' => 10, 'is_package' => false, 'price' => 1000]],
        ], $this->admin);

        $this->beras->delete();

        $this->actingAs($this->admin)
            ->get(route('monitoring.index', ['tab' => 'riwayat', 'search' => 'beras']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 1)
                ->where('movements.data.0.product', 'Beras'));
    }

    public function test_history_excludes_other_stores_movements(): void
    {
        $other = Store::factory()->create();
        $foreign = Product::factory()->create(['store_id' => $other->id]);
        \DB::table('stock_movements')->insert([
            'store_id' => $other->id,
            'product_id' => $foreign->id,
            'type' => 'stock_in',
            'quantity_change' => 5,
            'stock_before' => 0,
            'stock_after' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->get(route('monitoring.index', ['tab' => 'riwayat']))
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 0));
    }

    public function test_filters_are_validated(): void
    {
        $foreignCategory = Category::factory()->create();
        $this->actingAs($this->admin);

        $this->get(route('monitoring.index', ['status' => 'rusak']))->assertSessionHasErrors('status');
        $this->get(route('monitoring.index', ['tab' => 'riwayat', 'type' => 'dicuri']))->assertSessionHasErrors('type');
        $this->get(route('monitoring.index', ['category' => $foreignCategory->id]))->assertSessionHasErrors('category');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('monitoring.index'))->assertRedirect('/login');
    }
}