<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockInTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private User $admin;
    private User $karyawan;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create();
        $this->admin = User::factory()->create(['store_id' => $this->store->id]);
        $this->karyawan = User::factory()->karyawan()->create(['store_id' => $this->store->id]);
        $this->supplier = Supplier::factory()->create(['store_id' => $this->store->id]);
        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'stock' => 10,
            'unit' => 'pcs',
            'pack_unit' => 'dus',
            'units_per_pack' => 12,
            'purchase_price' => 900,
        ]);
    }

    private function payload(?array $items = null, array $override = []): array
    {
        return array_merge([
            'transaction_date' => today()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'notes' => 'Belanja mingguan',
            'items' => $items ?? [
                ['product_id' => $this->product->id, 'quantity' => 2, 'is_package' => true, 'price' => 12000],
            ],
        ], $override);
    }

    private function plainProduct(int $stock = 20): Product
    {
        return Product::factory()->create([
            'store_id' => $this->store->id,
            'pack_unit' => null,
            'units_per_pack' => 1,
            'stock' => $stock,
        ]);
    }

    public function test_package_quantity_is_converted_to_smallest_unit(): void
    {
        $response = $this->actingAs($this->admin)->post(route('stock-ins.store'), $this->payload());

        $stockIn = StockIn::firstOrFail();
        $response->assertRedirect(route('stock-ins.show', $stockIn));

        $this->assertMatchesRegularExpression('/^SI-\d{8}-0001$/', $stockIn->transaction_number);
        $this->assertEquals($this->store->id, $stockIn->store_id);
        $this->assertEquals(24000.0, (float) $stockIn->total_cost);

        $detail = $stockIn->details()->firstOrFail();
        $this->assertEquals(2, $detail->input_quantity);
        $this->assertTrue($detail->is_package);
        $this->assertEquals(12, $detail->units_per_pack);
        $this->assertEquals(24, $detail->quantity);
        $this->assertEquals(1000.0, (float) $detail->unit_cost);
        $this->assertEquals(24000.0, (float) $detail->subtotal);

        $product = $this->product->fresh();
        $this->assertSame(34, $product->stock);
        $this->assertEquals(1000.0, (float) $product->purchase_price);
    }

    public function test_stock_movement_is_recorded_with_before_and_after(): void
    {
        $this->actingAs($this->admin)->post(route('stock-ins.store'), $this->payload());

        $stockIn = StockIn::firstOrFail();
        $movement = StockMovement::where('product_id', $this->product->id)->firstOrFail();

        $this->assertSame(StockMovementType::StockIn, $movement->type);
        $this->assertSame(24, $movement->quantity_change);
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(34, $movement->stock_after);
        $this->assertEquals($stockIn->id, $movement->reference_id);
        $this->assertSame($stockIn->getMorphClass(), $movement->reference_type);
        $this->assertEquals($this->admin->id, $movement->user_id);
        $this->assertEquals($this->store->id, $movement->store_id);
    }

    public function test_quantity_in_smallest_unit_uses_price_per_piece(): void
    {
        $this->actingAs($this->admin)->post(route('stock-ins.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 5, 'is_package' => false, 'price' => 1100],
        ]));

        $product = $this->product->fresh();
        $this->assertSame(15, $product->stock);
        $this->assertEquals(1100.0, (float) $product->purchase_price);
        $this->assertEquals(5500.0, (float) StockIn::firstOrFail()->total_cost);
    }

    public function test_zero_price_keeps_previous_purchase_price(): void
    {
        $this->actingAs($this->admin)->post(route('stock-ins.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 5, 'is_package' => false, 'price' => 0],
        ]));

        $product = $this->product->fresh();
        $this->assertSame(15, $product->stock);
        $this->assertEquals(900.0, (float) $product->purchase_price);
    }

    public function test_multiple_items_are_recorded_in_one_transaction(): void
    {
        $plain = $this->plainProduct(20);

        $this->actingAs($this->admin)->post(route('stock-ins.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 1, 'is_package' => true, 'price' => 12000],
            ['product_id' => $plain->id, 'quantity' => 30, 'is_package' => false, 'price' => 500],
        ]));

        $this->assertSame(22, $this->product->fresh()->stock);
        $this->assertSame(50, $plain->fresh()->stock);
        $this->assertDatabaseCount('stock_ins', 1);
        $this->assertDatabaseCount('stock_in_details', 2);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertEquals(27000.0, (float) StockIn::firstOrFail()->total_cost);
    }

    public function test_karyawan_can_record_stock_in(): void
    {
        $this->actingAs($this->karyawan)
            ->post(route('stock-ins.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('stock_ins', 1);
        $this->assertSame(34, $this->product->fresh()->stock);
    }

    public function test_transaction_numbers_increment_per_store_and_date(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('stock-ins.store'), $this->payload());
        $this->post(route('stock-ins.store'), $this->payload());

        $prefix = 'SI-'.today()->format('Ymd').'-';
        $this->assertSame(
            [$prefix.'0001', $prefix.'0002'],
            StockIn::orderBy('id')->pluck('transaction_number')->all(),
        );

        // Toko lain memulai penomoran sendiri dari 0001.
        $otherStore = Store::factory()->create();
        $otherAdmin = User::factory()->create(['store_id' => $otherStore->id]);
        $otherProduct = Product::factory()->create(['store_id' => $otherStore->id, 'pack_unit' => null]);

        $this->actingAs($otherAdmin)->post(route('stock-ins.store'), $this->payload(
            [['product_id' => $otherProduct->id, 'quantity' => 1, 'is_package' => false, 'price' => 1000]],
            ['supplier_id' => null],
        ));

        $this->assertDatabaseHas('stock_ins', [
            'store_id' => $otherStore->id,
            'transaction_number' => $prefix.'0001',
        ]);
    }

    public function test_package_is_rejected_for_product_without_pack_unit_and_everything_rolls_back(): void
    {
        $plain = $this->plainProduct(20);

        $this->actingAs($this->admin)->post(route('stock-ins.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 1, 'is_package' => false, 'price' => 1000],
            ['product_id' => $plain->id, 'quantity' => 1, 'is_package' => true, 'price' => 5000],
        ]))->assertSessionHasErrors('items.1.is_package');

        // Baris pertama sempat diproses, tetapi harus ikut dibatalkan.
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(20, $plain->fresh()->stock);
        $this->assertDatabaseCount('stock_ins', 0);
        $this->assertDatabaseCount('stock_in_details', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stock_cannot_exceed_maximum(): void
    {
        $full = $this->plainProduct(1_999_999_990);

        $this->actingAs($this->admin)->post(route('stock-ins.store'), $this->payload([
            ['product_id' => $full->id, 'quantity' => 100, 'is_package' => false, 'price' => 100],
        ]))->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(1_999_999_990, $full->fresh()->stock);
        $this->assertDatabaseCount('stock_ins', 0);
    }

    public function test_validation_rules(): void
    {
        $foreignProduct = Product::factory()->create();   // toko lain
        $foreignSupplier = Supplier::factory()->create(); // toko lain
        $this->actingAs($this->admin);

        $this->post(route('stock-ins.store'), $this->payload([]))
            ->assertSessionHasErrors('items');

        $this->post(route('stock-ins.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 0, 'is_package' => false, 'price' => 1000],
        ]))->assertSessionHasErrors('items.0.quantity');

        $this->post(route('stock-ins.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 1, 'is_package' => false, 'price' => -5],
        ]))->assertSessionHasErrors('items.0.price');

        $this->post(route('stock-ins.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 1, 'is_package' => false, 'price' => 1000],
            ['product_id' => $this->product->id, 'quantity' => 2, 'is_package' => false, 'price' => 1000],
        ]))->assertSessionHasErrors('items.1.product_id');

        $this->post(route('stock-ins.store'), $this->payload([
            ['product_id' => $foreignProduct->id, 'quantity' => 1, 'is_package' => false, 'price' => 1000],
        ]))->assertSessionHasErrors('items.0.product_id');

        $this->post(route('stock-ins.store'), $this->payload(null, ['supplier_id' => $foreignSupplier->id]))
            ->assertSessionHasErrors('supplier_id');

        $this->post(route('stock-ins.store'), $this->payload(null, ['transaction_date' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors('transaction_date');

        $this->assertDatabaseCount('stock_ins', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_deleted_product_cannot_be_used(): void
    {
        $this->product->delete();

        $this->actingAs($this->admin)
            ->post(route('stock-ins.store'), $this->payload())
            ->assertSessionHasErrors('items.0.product_id');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('stock-ins.index'))->assertRedirect('/login');
        $this->post(route('stock-ins.store'), $this->payload())->assertRedirect('/login');
    }

    public function test_stock_in_from_other_store_is_not_accessible(): void
    {
        $other = Store::factory()->create();
        $foreign = new StockIn([
            'transaction_number' => 'SI-20260101-0001',
            'transaction_date' => today()->toDateString(),
            'total_cost' => 0,
        ]);
        $foreign->store_id = $other->id;
        $foreign->save();

        $this->actingAs($this->admin)
            ->get(route('stock-ins.show', $foreign->id))
            ->assertNotFound();
    }

    public function test_show_displays_transaction_details(): void
    {
        $this->actingAs($this->admin)->post(route('stock-ins.store'), $this->payload());
        $stockIn = StockIn::firstOrFail();

        $this->get(route('stock-ins.show', $stockIn))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StockIns/Show')
                ->where('stockIn.transaction_number', $stockIn->transaction_number)
                ->has('stockIn.details', 1)
                ->where('stockIn.details.0.quantity', 24)
                ->where('stockIn.details.0.input_quantity', 2)
                ->where('stockIn.details.0.is_package', true));
    }

    public function test_index_supports_search_and_date_filter(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('stock-ins.store'), $this->payload(null, ['transaction_date' => today()->subDays(3)->toDateString()]));
        $this->post(route('stock-ins.store'), $this->payload());

        $this->get(route('stock-ins.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('StockIns/Index')
                ->has('stockIns.data', 2)
                ->where('summary.count', 2));

        $this->get(route('stock-ins.index', ['from' => today()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('stockIns.data', 1));

        $this->get(route('stock-ins.index', ['to' => today()->subDay()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('stockIns.data', 1));

        $this->get(route('stock-ins.index', ['search' => $this->supplier->name]))
            ->assertInertia(fn (Assert $page) => $page->has('stockIns.data', 2));

        $this->get(route('stock-ins.index', ['search' => 'tidak-ada']))
            ->assertInertia(fn (Assert $page) => $page->has('stockIns.data', 0));
    }

    public function test_dashboard_chart_counts_stock_in(): void
    {
        $this->actingAs($this->admin)->post(route('stock-ins.store'), $this->payload());

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('movementChart.masuk.6', 24));
    }
}
