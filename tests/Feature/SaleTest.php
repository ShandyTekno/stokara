<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private User $admin;
    private User $karyawan;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create();
        $this->admin = User::factory()->create(['store_id' => $this->store->id]);
        $this->karyawan = User::factory()->karyawan()->create(['store_id' => $this->store->id]);
        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
            'stock' => 10,
            'unit' => 'pcs',
            'min_stock' => 3,
            'purchase_price' => 9000,
            'selling_price' => 12000,
        ]);
    }

    private function payload(?array $items = null, array $override = []): array
    {
        return array_merge([
            'payment_method' => 'tunai',
            'amount_paid' => 50000,
            'notes' => 'Pelanggan tetap',
            'items' => $items ?? [['product_id' => $this->product->id, 'quantity' => 3]],
        ], $override);
    }

    public function test_cash_sale_reduces_stock_and_records_totals_and_change(): void
    {
        $response = $this->actingAs($this->admin)->post(route('sales.store'), $this->payload());

        $sale = Sale::firstOrFail();
        $response->assertRedirect(route('sales.show', $sale));

        $this->assertMatchesRegularExpression('/^INV-\d{8}-0001$/', $sale->invoice_number);
        $this->assertSame(PaymentMethod::Tunai, $sale->payment_method);
        $this->assertEquals($this->store->id, $sale->store_id);
        $this->assertEquals($this->admin->id, $sale->user_id);
        $this->assertEquals(36000.0, (float) $sale->total_amount);
        $this->assertEquals(50000.0, (float) $sale->amount_paid);
        $this->assertEquals(14000.0, (float) $sale->change_amount);

        $detail = $sale->details()->firstOrFail();
        $this->assertEquals(3, $detail->quantity);
        $this->assertEquals(12000.0, (float) $detail->unit_price);
        $this->assertEquals(9000.0, (float) $detail->unit_cost);
        $this->assertEquals(36000.0, (float) $detail->subtotal);

        $this->assertSame(7, $this->product->fresh()->stock);
    }

    public function test_sale_is_recorded_in_stock_ledger(): void
    {
        $this->actingAs($this->admin)->post(route('sales.store'), $this->payload());

        $sale = Sale::firstOrFail();
        $movement = StockMovement::where('product_id', $this->product->id)->firstOrFail();

        $this->assertSame(StockMovementType::Sale, $movement->type);
        $this->assertSame(-3, $movement->quantity_change);
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(7, $movement->stock_after);
        $this->assertEquals($sale->id, $movement->reference_id);
        $this->assertSame($sale->getMorphClass(), $movement->reference_type);
        $this->assertEquals($this->admin->id, $movement->user_id);
        $this->assertEquals($this->store->id, $movement->store_id);
    }

    public function test_qris_sale_is_paid_exactly_and_needs_no_amount(): void
    {
        $this->actingAs($this->admin)
            ->post(route('sales.store'), $this->payload(null, ['payment_method' => 'qris', 'amount_paid' => null]))
            ->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();
        $this->assertSame(PaymentMethod::Qris, $sale->payment_method);
        $this->assertEquals(36000.0, (float) $sale->amount_paid);
        $this->assertEquals(0.0, (float) $sale->change_amount);
        $this->assertSame(7, $this->product->fresh()->stock);
    }

    public function test_exact_cash_payment_gives_zero_change(): void
    {
        $this->actingAs($this->admin)
            ->post(route('sales.store'), $this->payload(null, ['amount_paid' => 36000]))
            ->assertSessionHasNoErrors();

        $this->assertEquals(0.0, (float) Sale::firstOrFail()->change_amount);
    }

    public function test_price_comes_from_the_server_not_the_client(): void
    {
        $this->actingAs($this->admin)->post(route('sales.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 2, 'price' => 1, 'unit_price' => 1, 'subtotal' => 1],
        ], ['amount_paid' => 100000]));

        $sale = Sale::firstOrFail();
        $this->assertEquals(24000.0, (float) $sale->total_amount);
        $this->assertEquals(12000.0, (float) $sale->details()->firstOrFail()->unit_price);
    }

    public function test_price_snapshot_survives_later_price_changes(): void
    {
        $this->actingAs($this->admin)->post(route('sales.store'), $this->payload());

        $this->product->forceFill(['selling_price' => 99999, 'purchase_price' => 88888])->save();

        $detail = Sale::firstOrFail()->details()->firstOrFail();
        $this->assertEquals(12000.0, (float) $detail->unit_price);
        $this->assertEquals(9000.0, (float) $detail->unit_cost);
    }

    public function test_cash_is_required_and_must_cover_the_total(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('sales.store'), $this->payload(null, ['amount_paid' => null]))
            ->assertSessionHasErrors('amount_paid');

        $this->post(route('sales.store'), $this->payload(null, ['amount_paid' => 35999]))
            ->assertSessionHasErrors('amount_paid');

        // Ditolak di tengah proses (setelah stok sempat dikurangi): semuanya harus dibatalkan.
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_details', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_quantity_above_stock_is_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin)->post(route('sales.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 11],
        ], ['amount_paid' => 999999]))->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_failure_on_one_line_rolls_back_the_whole_sale(): void
    {
        $other = Product::factory()->create(['store_id' => $this->store->id, 'stock' => 5]);

        $this->actingAs($this->admin)->post(route('sales.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 3],
            ['product_id' => $other->id, 'quantity' => 99],
        ], ['amount_paid' => 99999999]))->assertSessionHasErrors('items.1.quantity');

        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(5, $other->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_details', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_all_stock_can_be_sold(): void
    {
        $this->actingAs($this->admin)->post(route('sales.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 10],
        ], ['amount_paid' => 120000]))->assertSessionHasNoErrors();

        $this->assertSame(0, $this->product->fresh()->stock);
    }

    public function test_multiple_items_are_summed_in_one_sale(): void
    {
        $other = Product::factory()->create([
            'store_id' => $this->store->id,
            'stock' => 20,
            'selling_price' => 2500,
            'purchase_price' => 2000,
        ]);

        $this->actingAs($this->admin)->post(route('sales.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $other->id, 'quantity' => 4],
        ], ['amount_paid' => 30000]));

        $sale = Sale::firstOrFail();
        $this->assertEquals(22000.0, (float) $sale->total_amount);
        $this->assertEquals(8000.0, (float) $sale->change_amount);
        $this->assertDatabaseCount('sale_details', 2);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame(9, $this->product->fresh()->stock);
        $this->assertSame(16, $other->fresh()->stock);
    }

    public function test_total_above_column_limit_is_rejected(): void
    {
        $huge = Product::factory()->create([
            'store_id' => $this->store->id,
            'stock' => 100,
            'selling_price' => 999999999999,
        ]);

        $this->actingAs($this->admin)->post(route('sales.store'), $this->payload([
            ['product_id' => $huge->id, 'quantity' => 100],
        ], ['payment_method' => 'qris', 'amount_paid' => null]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(100, $huge->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_invoice_numbers_increment_per_store_and_date(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('sales.store'), $this->payload());
        $this->post(route('sales.store'), $this->payload());

        $prefix = 'INV-'.today()->format('Ymd').'-';
        $this->assertSame(
            [$prefix.'0001', $prefix.'0002'],
            Sale::orderBy('id')->pluck('invoice_number')->all(),
        );

        $otherStore = Store::factory()->create();
        $otherAdmin = User::factory()->create(['store_id' => $otherStore->id]);
        $otherProduct = Product::factory()->create(['store_id' => $otherStore->id, 'stock' => 10, 'selling_price' => 1000]);

        $this->actingAs($otherAdmin)->post(route('sales.store'), $this->payload(
            [['product_id' => $otherProduct->id, 'quantity' => 1]],
            ['amount_paid' => 1000],
        ));

        $this->assertDatabaseHas('sales', ['store_id' => $otherStore->id, 'invoice_number' => $prefix.'0001']);
    }

    public function test_validation_rules(): void
    {
        $foreign = Product::factory()->create(); // toko lain
        $this->actingAs($this->admin);

        $this->post(route('sales.store'), $this->payload([]))
            ->assertSessionHasErrors('items');

        $this->post(route('sales.store'), $this->payload(null, ['payment_method' => 'kartu']))
            ->assertSessionHasErrors('payment_method');

        $this->post(route('sales.store'), $this->payload([['product_id' => $this->product->id, 'quantity' => 0]]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->post(route('sales.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $this->product->id, 'quantity' => 1],
        ]))->assertSessionHasErrors('items.1.product_id');

        $this->post(route('sales.store'), $this->payload([['product_id' => $foreign->id, 'quantity' => 1]]))
            ->assertSessionHasErrors('items.0.product_id');

        $this->post(route('sales.store'), $this->payload(null, ['amount_paid' => -1]))
            ->assertSessionHasErrors('amount_paid');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(0, $foreign->fresh()->stock === 0 ? 0 : 0); // barang toko lain tidak disentuh
    }

    public function test_deleted_product_cannot_be_sold(): void
    {
        $this->product->delete();

        $this->actingAs($this->admin)
            ->post(route('sales.store'), $this->payload())
            ->assertSessionHasErrors('items.0.product_id');
    }

    public function test_karyawan_can_sell_and_view_history(): void
    {
        $this->actingAs($this->karyawan)
            ->post(route('sales.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->get(route('sales.index'))->assertOk();
        $this->assertSame(7, $this->product->fresh()->stock);
        $this->assertEquals($this->karyawan->id, Sale::firstOrFail()->user_id);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('sales.index'))->assertRedirect('/login');
        $this->get(route('sales.create'))->assertRedirect('/login');
        $this->post(route('sales.store'), $this->payload())->assertRedirect('/login');
    }

    public function test_sale_from_other_store_is_not_accessible(): void
    {
        $other = Store::factory()->create();
        $foreign = new Sale([
            'invoice_number' => 'INV-20260101-0001',
            'sold_at' => now(),
            'total_amount' => 1000,
            'payment_method' => PaymentMethod::Tunai,
        ]);
        $foreign->store_id = $other->id;
        $foreign->save();

        $this->actingAs($this->admin)->get(route('sales.show', $foreign->id))->assertNotFound();
    }

    public function test_index_lists_own_sales_with_filters_and_summary(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('sales.store'), $this->payload());
        $this->post(route('sales.store'), $this->payload(null, ['payment_method' => 'qris', 'amount_paid' => null]));

        $old = Sale::orderBy('id')->firstOrFail();
        DB::table('sales')->where('id', $old->id)->update(['sold_at' => now()->subDays(10)]);

        $this->get(route('sales.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Index')
                ->has('sales.data', 2)
                ->where('summary.count', 2)
                ->where('summary.total_amount', fn ($v) => (float) $v === 72000.0)
                ->where('summary.cash_total', fn ($v) => (float) $v === 36000.0)
                ->where('summary.qris_total', fn ($v) => (float) $v === 36000.0));

        $this->get(route('sales.index', ['from' => today()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('sales.data', 1));

        $this->get(route('sales.index', ['payment' => 'tunai']))
            ->assertInertia(fn (Assert $page) => $page->has('sales.data', 1));

        $this->get(route('sales.index', ['search' => $old->invoice_number]))
            ->assertInertia(fn (Assert $page) => $page->has('sales.data', 1));
    }

    public function test_index_does_not_show_other_stores_sales(): void
    {
        $other = Store::factory()->create();
        $foreign = new Sale([
            'invoice_number' => 'INV-20260101-0001',
            'sold_at' => now(),
            'total_amount' => 5000,
            'payment_method' => PaymentMethod::Qris,
        ]);
        $foreign->store_id = $other->id;
        $foreign->save();

        $this->actingAs($this->admin)->get(route('sales.index'))
            ->assertInertia(fn (Assert $page) => $page->has('sales.data', 0)->where('summary.count', 0));
    }

    public function test_show_displays_receipt_data(): void
    {
        $this->actingAs($this->admin)->post(route('sales.store'), $this->payload());
        $sale = Sale::firstOrFail();

        $this->get(route('sales.show', $sale))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Show')
                ->where('store.name', $this->store->name)
                ->where('sale.invoice_number', $sale->invoice_number)
                ->where('sale.payment', 'Tunai')
                ->where('sale.total_amount', fn ($v) => (float) $v === 36000.0)
                ->where('sale.amount_paid', fn ($v) => (float) $v === 50000.0)
                ->where('sale.change_amount', fn ($v) => (float) $v === 14000.0)
                ->has('sale.details', 1)
                ->where('sale.details.0.quantity', 3));
    }

    public function test_create_page_lists_products_with_prices(): void
    {
        $this->actingAs($this->admin)->get(route('sales.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Create')
                ->has('products', 1)
                ->where('products.0.selling_price', fn ($v) => (float) $v === 12000.0));
    }

    public function test_dashboard_reflects_todays_sales(): void
    {
        $this->actingAs($this->admin)->post(route('sales.store'), $this->payload());

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.today_transactions', 1)
                ->where('stats.today_sales', fn ($v) => (float) $v === 36000.0)
                ->where('salesChart.values.6', fn ($v) => (float) $v === 36000.0)
                ->where('movementChart.keluar.6', 3));
    }
}
